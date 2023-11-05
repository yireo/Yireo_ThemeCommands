<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

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
    private ThemeResourceModel\CollectionFactory $themeCollectionFactory;

    public function __construct(
        ThemeCollectionFactory $themeCollectionFactory,
        string $name = null
    ) {
        parent::__construct($name);
        $this->themeCollectionFactory = $themeCollectionFactory;
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
        $table->setHeaders(['ID', 'Theme', 'Path', 'Area']);

        /** @var ThemeCollection $themeCollection */
        $themeCollection = $this->themeCollectionFactory->create();
        foreach ($themeCollection as $theme) {
            $table->addRow([
                $theme->getId(),
                $theme->getThemeTitle(),
                $theme->getThemePath(),
                $theme->getArea(),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
