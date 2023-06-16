<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\ScopeResolverPool;
use Magento\Indexer\Model\IndexerFactory;
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

    public function __construct(
        ScopeResolverPool $scopeResolverPool,
        ConfigInterface $config,
        CacheManager $cacheManager,
        IndexerFactory $indexerFactory,
        string $name = null)
    {
        parent::__construct($name);
        $this->scopeResolverPool = $scopeResolverPool;
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->indexerFactory = $indexerFactory;
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('theme:change');
        $this->setDescription('Change a StoreView to use a specific theme');
        $this->addArgument('theme_id', InputArgument::REQUIRED, 'Theme ID');
        $this->addArgument('scope_id', InputArgument::REQUIRED, 'Scope ID');
        $this->addArgument('scope', InputArgument::OPTIONAL, 'Scope (store, store_group, website)');
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
        $themeId = $input->getArgument('theme_id');
        $scopeId = $input->getArgument('scope_id');
        $scope = 'stores';

        $this->config->saveConfig('design/theme/theme_id', $themeId, $scope, $scopeId);

        $this->cacheManager->clean(['config', 'layout', 'block_html']);

        /** @var \Magento\Indexer\Model\Indexer $indexer */
        $indexer = $this->indexerFactory->create();
        $indexer->load('design_config_grid');
        $indexer->reindexAll();

        return Command::SUCCESS;
    }
}
