<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\ScopeResolverPool;
use Magento\Framework\View\Design\Theme\ListInterface;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Theme\Model\ResourceModel\Theme as ThemeResourceModel;
use Magento\Theme\Model\ThemeFactory as ThemeModelFactory;
use Magento\Theme\Model\Theme;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;

class ThemeChangeCommand extends Command
{
    private ScopeResolverPool $scopeResolverPool;
    private ConfigInterface $config;
    private CacheManager $cacheManager;
    private IndexerFactory $indexerFactory;
    private ThemeResourceModel $themeResourceModel;
    private ThemeModelFactory $themeFactory;

    public function __construct(
        ScopeResolverPool $scopeResolverPool,
        ConfigInterface $config,
        CacheManager $cacheManager,
        IndexerFactory $indexerFactory,
        ThemeResourceModel $themeResourceModel,
        ThemeModelFactory $themeFactory,
        string $name = null
    ) {
        parent::__construct($name);
        $this->scopeResolverPool = $scopeResolverPool;
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->indexerFactory = $indexerFactory;
        $this->themeResourceModel = $themeResourceModel;
        $this->themeFactory = $themeFactory;
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('theme:change');
        $this->setDescription('Change a StoreView to use a specific theme');
        $this->addArgument('theme_name', InputArgument::REQUIRED, 'Theme name (example: Magento/luma');
        $this->addArgument('scope_id', InputArgument::OPTIONAL, 'Scope ID (example: 42)');
        $this->addArgument('scope', InputArgument::OPTIONAL, 'Scope (values: stores, websites, default)');
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themeName = trim($input->getArgument('theme_name'));
        $themeModel = $this->themeFactory->create();
        $this->themeResourceModel->load($themeModel, $themeName, 'theme_path');
        $themeId = $themeModel->getId();

        if (!$themeId > 0) {
            $output->writeln('<error>Not a valid theme: ' . $themeName . '</error>');
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

        $this->config->saveConfig('design/theme/theme_id', $themeId, $scope, $scopeId);
        $this->cacheManager->clean(['config', 'layout', 'block_html']);

        /** @var Indexer $indexer */
        $indexer = $this->indexerFactory->create();
        $indexer->load('design_config_grid');
        $indexer->reindexAll();

        return Command::SUCCESS;
    }
}
