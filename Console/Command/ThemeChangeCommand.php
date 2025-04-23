<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Store\Model\ResourceModel\Store as StoreResourceModel;
use Magento\Store\Model\StoreFactory as StoreModelFactory;
use Magento\Store\Model\ResourceModel\Website as WebsiteResourceModel;
use Magento\Store\Model\WebsiteFactory as WebsiteModelFactory;
use Magento\Theme\Model\ResourceModel\Theme as ThemeResourceModel;
use Magento\Theme\Model\ThemeFactory as ThemeModelFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Throwable;

class ThemeChangeCommand extends Command
{
    private ConfigInterface $config;
    private CacheManager $cacheManager;
    private IndexerFactory $indexerFactory;
    private ThemeResourceModel $themeResourceModel;
    private ThemeModelFactory $themeFactory;
    private WebsiteResourceModel $websiteResourceModel;
    private WebsiteModelFactory $websiteFactory;
    private StoreResourceModel $storeResourceModel;
    private StoreModelFactory $storeFactory;
    private ResourceConnection $resourceConnection;

    public function __construct(
        ConfigInterface $config,
        CacheManager $cacheManager,
        IndexerFactory $indexerFactory,
        ThemeResourceModel $themeResourceModel,
        ThemeModelFactory $themeFactory,
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
        $this->themeResourceModel = $themeResourceModel;
        $this->themeFactory = $themeFactory;
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
        $this->addArgument('theme_name', InputArgument::REQUIRED, 'Theme name (example: Magento/luma');
        $this->addArgument('scope', InputArgument::OPTIONAL, 'Scope (values: stores, websites, default)');
        $this->addArgument('scope_id', InputArgument::OPTIONAL, 'Scope ID (example: 42)');
        $this->addOption('reset', null, InputOption::VALUE_OPTIONAL, 'Reset all themes', false);
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
        $reset = $input->getOption('reset') !== false;

        $themeName = trim($input->getArgument('theme_name'));
        $themeId = $this->getThemeId($themeName);

        if (is_null($themeId)) {
            $output->writeln('<error>Not a valid theme: '.$themeName.'</error>');

            return Command::FAILURE;
        }

        $scopeId = $input->getArgument('scope_id');
        $scope = $input->getArgument('scope');
        if (empty($scope) && !empty($scopeId)) {
            $scope = 'stores';
        }

        if (empty($scope) && empty($scopeId)) {
            $scopeId = 0;
            $scope = 'default';
        }

        if (!is_numeric($scopeId)) {
            switch ($scope) {
                case 'websites':
                    $scopeId = $this->getWebsiteId($scopeId);
                    break;
                case 'stores':
                    $scopeId = $this->getStoreId($scopeId);
                    break;
                default:
                    $scopeId = 0;
            }
        }

        if (is_null($scopeId)) {
            $output->writeln('<error>Not a valid scope_id</error>');

            return Command::FAILURE;
        }

        if (!in_array($scope, ['default', 'website', 'stores'])) {
            $output->writeln('<error>Not a valid scope. Can only be: default, website, stores</error>');

            return Command::FAILURE;
        }

        $output->writeln('Saving '.$themeId.' for scope '.$scope.' '.$scopeId);
        $this->config->saveConfig('design/theme/theme_id', $themeId, $scope, $scopeId);

        if ($scope === 'default' && $reset === true) {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('core_config_data');
            $query = 'DELETE FROM `'.$table.'`';
            $query .= ' WHERE `path` = "design/theme/theme_id" AND `scope` != "default"';
            $connection->query($query);
        }

        $this->cacheManager->clean(['config', 'layout', 'block_html']);

        $indexer = $this->indexerFactory->create();
        $indexer->load('design_config_grid');
        $indexer->reindexAll();
        $indexer->reindexAll();

        return Command::SUCCESS;
    }

    private function getThemeId(string $themeName): ?int
    {
        $themeModel = $this->themeFactory->create();
        $this->themeResourceModel->load($themeModel, $themeName, 'theme_path');

        return $themeModel->getId() ? (int)$themeModel->getId() : null;
    }

    private function getWebsiteId(string $scopeId): ?int
    {
        $websiteModel = $this->websiteFactory->create();
        $this->websiteResourceModel->load($websiteModel, $scopeId, 'code');

        return $websiteModel->getId() ? (int)$websiteModel->getId() : null;
    }

    private function getStoreId(string $scopeId): ?int
    {
        $storeModel = $this->storeFactory->create();
        $this->storeResourceModel->load($storeModel, $scopeId, 'code');

        return $storeModel->getId() ? (int)$storeModel->getId() : null;
    }
}
