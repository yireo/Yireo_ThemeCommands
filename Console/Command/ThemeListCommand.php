<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Theme\Model\Theme;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addOption('json', null, InputOption::VALUE_OPTIONAL, 'Output as JSON');
        $this->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Theme type (hyva, luma)');
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
        $selectedThemeType = trim((string)$input->getOption('type'));
        $themes = $this->getThemes()->getItems();
        if ($selectedThemeType) {
            $themes = array_filter($themes, fn(Theme $theme) => $this->getThemeType($theme) === $selectedThemeType);
        }

        $json = $input->getOption('json');
        if ((bool)$json) {
            $this->renderJson($output, $themes);
            return Command::SUCCESS;
        }


        $this->renderTable($output, $themes);

        return Command::SUCCESS;
    }

    private function renderJson(OutputInterface $output, array $themes): void
    {
        $themeData = [];
        foreach ($themes as $theme) {
            $themeData[] = [
                'id' => $theme->getId(),
                'name' => $theme->getThemeTitle(),
                'path' => $theme->getThemePath(),
                'area' => $theme->getArea(),
                'type' => $this->getThemeType($theme),
                'parentId' => $theme->getParentId(),
                'active' => $this->isActive((int)$theme->getId())
            ];
        }

        $json = (string)json_encode($themeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $output->writeln($json);
    }

    private function renderTable(OutputInterface $output, array $themes): void
    {
        $table = new Table($output);
        $table->setHeaders([
            'ID',
            'Theme',
            'Path',
            'Area',
            'Parent ID',
            'Type',
            'Active'
        ]);

        foreach ($themes as $theme) {
            $table->addRow([
                $theme->getId(),
                $theme->getThemeTitle(),
                $theme->getThemePath(),
                $theme->getArea(),
                $theme->getParentId(),
                $this->getThemeType($theme),
                $this->isActive((int)$theme->getId()) ? 'Yes' : 'No'
            ]);
        }

        $table->render();
    }

    private function getThemes(): ThemeCollection
    {
        return $this->themeCollectionFactory->create();
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

    private function getThemeType(Theme $theme): string
    {
        if (str_starts_with((string) $theme->getThemePath(), 'Hyva/')) {
            return 'hyva';
        }

        if (str_starts_with((string) $theme->getThemePath(), 'Magento/')) {
            return 'luma';
        }

        if ($theme->getParentTheme()) {
            return $this->getThemeType($theme->getParentTheme());
        }

        return 'unknown';
    }
}
