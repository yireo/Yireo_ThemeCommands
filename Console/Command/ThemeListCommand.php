<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ResourceModel\Theme as ThemeResourceModel;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ThemeListCommand extends Command
{
    public function __construct(
        private readonly ThemeCollectionFactory $themeCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure(): void
    {
        $this->setName('theme:list');
        $this->setDescription('Show all available themes');
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
        $table->setHeaders(['ID', 'Theme', 'Path', 'Area', 'Active']);

        /** @var ThemeCollection $themeCollection */
        $themeCollection = $this->themeCollectionFactory->create();
        foreach ($themeCollection as $theme) {
            $table->addRow([
                $theme->getId(),
                $theme->getThemeTitle(),
                $theme->getThemePath(),
                $theme->getArea(),
                $this->isActive((int)$theme->getId()) ? 'Yes' : 'No'
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function isActive(int $themeId): bool
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeThemeId = $this->scopeConfig->getValue(
                DesignInterface::XML_PATH_THEME_ID,
                ScopeInterface::SCOPE_STORE,
                $store->getCode(),
            );

            if ($themeId === (int)$storeThemeId) {
                return true;
            }
        }

        return false;
    }
}
