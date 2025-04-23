<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreRepository;
use Magento\Store\Model\WebsiteRepository;
use Magento\Theme\Model\ResourceModel\Design\Config\Scope\CollectionFactory;
use Magento\Theme\Model\Theme\ThemeProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ThemeDesignConfigCommand extends Command
{

    public function __construct(
        private CollectionFactory $designCollectionFactory,
        private WebsiteRepository $websiteRepository,
        private StoreRepository $storeRepository,
        private ThemeProvider $themeProvider,
        private ResourceConnection $resourceConnection,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure(): void
    {
        $this->setName('theme:design_config');
        $this->setDescription('Show currently active themes');
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
        $table = new Table($output);
        $table->setHeaders([
            'Website',
            'Website ID',
            'Store View',
            'Store View ID',
            'Theme',
            'Theme ID',
            'Override',
        ]);

        $designCollection = $this->designCollectionFactory->create();
        foreach ($designCollection as $designConfig) {
            try {
                $website = $this->websiteRepository->get($designConfig->getStoreWebsiteId());
                $websiteId = $website->getId();
                $websiteName = $website->getName();
            } catch (\Exception $exception) {
                $websiteId = '';
                $websiteName = '';
            }

            try {
                $store = $this->storeRepository->getById($designConfig->getStoreId());
                $storeId = $store->getId();
                $storeName = $store->getName();
            } catch (\Exception $exception) {
                $storeId = '';
                $storeName = '';
            }

            $theme = $this->themeProvider->getThemeById($designConfig->getThemeThemeId());
            $themeId = $theme->getId();
            $themeName = $theme->getCode();

            $override = null;
            if ($websiteId > 0) {
                $override = $this->isCustomized('websites', $websiteId, $themeId) ? 'x' : '';
            }

            if ($storeId > 0) {
                $override = $this->isCustomized('stores', $storeId, $themeId) ? 'x' : '';
            }

            $table->addRow([
                $websiteId,
                $websiteName,
                $storeId,
                $storeName,
                $themeId,
                $themeName,
                $override
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function isCustomized(string $scope, $scopeId, $value): bool
    {
        if ($scope === 'default') {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('core_config_data');
        $query = 'SELECT `value` FROM `'.$table.'`';
        $query .= ' WHERE `path` = "design/theme/theme_id" AND `scope` = "'.$scope.'" AND `scope_id` = "'.$scopeId.'"';
        $col = $connection->fetchCol($query);

        if (empty($col)) {
            return false;
        }

        return true;
    }
}
