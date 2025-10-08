<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Composer\Console\Input\InputArgument;
use Magento\Framework\Component\ComponentRegistrar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ThemePathCommand extends Command
{
    private ComponentRegistrar $componentRegistrar;

    public function __construct(
        ComponentRegistrar $componentRegistrar,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * Initialization of the command.
     */
    protected function configure(): void
    {
        $this->setName('theme:path');
        $this->setDescription('Show path of a given theme');
        $this->addArgument('theme', InputArgument::REQUIRED, 'Theme name');
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
        $theme = 'frontend/'.$input->getArgument('theme');
        $path = (string)$this->componentRegistrar->getPath(ComponentRegistrar::THEME, $theme);
        $output->writeln($path);

        return Command::SUCCESS;
    }
}
