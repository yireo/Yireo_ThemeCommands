<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Store\Model\ResourceModel\Store as StoreResourceModel;
use Magento\Store\Model\StoreFactory as StoreModelFactory;
use Magento\Store\Model\ResourceModel\Website as WebsiteResourceModel;
use Magento\Store\Model\WebsiteFactory as WebsiteModelFactory;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;
use Yireo\ThemeCommands\Console\OutputSanitizer;

class ThemeChangeCommand extends Command
{
    private const SCOPES = ['default', 'websites', 'stores'];

    private ConfigInterface $config;
    private CacheManager $cacheManager;
    private IndexerFactory $indexerFactory;
    private ThemeCollectionFactory $themeCollectionFactory;
    private WebsiteResourceModel $websiteResourceModel;
    private WebsiteModelFactory $websiteFactory;
    private StoreResourceModel $storeResourceModel;
    private StoreModelFactory $storeFactory;
    private ResourceConnection $resourceConnection;

    public function __construct(
        ConfigInterface $config,
        CacheManager $cacheManager,
        IndexerFactory $indexerFactory,
        ThemeCollectionFactory $themeCollectionFactory,
        WebsiteResourceModel $websiteResourceModel,
        WebsiteModelFactory $websiteFactory,
        StoreResourceModel $storeResourceModel,
        StoreModelFactory $storeFactory,
        ResourceConnection $resourceConnection,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->indexerFactory = $indexerFactory;
        $this->themeCollectionFactory = $themeCollectionFactory;
        $this->websiteResourceModel = $websiteResourceModel;
        $this->websiteFactory = $websiteFactory;
        $this->storeResourceModel = $storeResourceModel;
        $this->storeFactory = $storeFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Initialization of the command.
     */
    protected function configure(): void
    {
        $this->setName('theme:change');
        $this->setDescription('Change a StoreView to use a specific theme');
        $this->addArgument('theme_name', InputArgument::REQUIRED, 'Theme name (example: Magento/luma)');
        $this->addArgument('scope', InputArgument::OPTIONAL, 'Scope (values: stores, websites, default)');
        $this->addArgument('scope_id', InputArgument::OPTIONAL, 'Scope ID or code (example: 42)');
        $this->addOption(
            'reset',
            null,
            InputOption::VALUE_NONE,
            'Remove the theme of all other scopes (only with the default scope)'
        );
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reset = (bool)$input->getOption('reset');

        $themeName = trim((string)$input->getArgument('theme_name'), " \f\n\r\t\v\x00");
        $themeId = $this->getThemeId($themeName);

        if (is_null($themeId)) {
            $output->writeln('<error>Not a valid theme: ' . OutputSanitizer::sanitize($themeName) . '</error>');

            return Command::FAILURE;
        }

        $scopeId = trim((string)$input->getArgument('scope_id'), " \f\n\r\t\v\x00");
        $scope = trim((string)$input->getArgument('scope'), " \f\n\r\t\v\x00");
        if ($scope === '') {
            $scope = $scopeId === '' ? 'default' : 'stores';
        }

        if (!in_array($scope, self::SCOPES, true)) {
            $output->writeln('<error>Not a valid scope. Can only be: default, websites, stores</error>');

            return Command::FAILURE;
        }

        $resolvedScopeId = $this->resolveScopeId($scope, $scopeId);
        if (is_null($resolvedScopeId)) {
            $output->writeln('<error>Not a valid scope_id</error>');

            return Command::FAILURE;
        }

        if ($reset && $scope !== 'default') {
            $output->writeln('<error>The option --reset can only be used with the default scope</error>');

            return Command::FAILURE;
        }

        if ($reset && $input->isInteractive() && !$this->confirmReset($input, $output)) {
            $output->writeln('Aborted');

            return Command::FAILURE;
        }

        $output->writeln('Saving ' . $themeId . ' for scope ' . $scope . ' ' . $resolvedScopeId);
        $this->saveTheme($themeId, $scope, $resolvedScopeId, $reset);

        $this->cacheManager->clean(['config', 'layout', 'block_html']);

        $indexer = $this->indexerFactory->create();
        $indexer->load('design_config_grid');
        $indexer->reindexAll();

        return Command::SUCCESS;
    }

    private function saveTheme(int $themeId, string $scope, int $scopeId, bool $reset): void
    {
        if ($reset === false) {
            $this->config->saveConfig('design/theme/theme_id', $themeId, $scope, $scopeId);

            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $this->config->saveConfig('design/theme/theme_id', $themeId, $scope, $scopeId);
            $connection->delete($this->resourceConnection->getTableName('core_config_data'), [
                'path = ?' => 'design/theme/theme_id',
                'scope != ?' => 'default',
            ]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function confirmReset(InputInterface $input, OutputInterface $output): bool
    {
        $question = new ConfirmationQuestion(
            'This removes the theme setting of all websites and store views. Continue? [y/N] ',
            false
        );

        return (bool)(new QuestionHelper())->ask($input, $output, $question);
    }

    private function resolveScopeId(string $scope, string $scopeId): ?int
    {
        if ($scope === 'default') {
            return $scopeId === '' || $scopeId === '0' ? 0 : null;
        }

        if ($scopeId === '') {
            return null;
        }

        $field = ctype_digit($scopeId) ? null : 'code';

        return $scope === 'websites' ? $this->getWebsiteId($scopeId, $field) : $this->getStoreId($scopeId, $field);
    }

    private function getThemeId(string $themeName): ?int
    {
        $theme = $this->themeCollectionFactory->create()->getThemeByFullPath(Area::AREA_FRONTEND . '/' . $themeName);

        return $theme->getId() ? (int)$theme->getId() : null;
    }

    private function getWebsiteId(string $scopeId, ?string $field): ?int
    {
        $websiteModel = $this->websiteFactory->create();
        $this->websiteResourceModel->load($websiteModel, $scopeId, $field);

        return $websiteModel->getId() ? (int)$websiteModel->getId() : null;
    }

    private function getStoreId(string $scopeId, ?string $field): ?int
    {
        $storeModel = $this->storeFactory->create();
        $this->storeResourceModel->load($storeModel, $scopeId, $field);

        return $storeModel->getId() ? (int)$storeModel->getId() : null;
    }
}
