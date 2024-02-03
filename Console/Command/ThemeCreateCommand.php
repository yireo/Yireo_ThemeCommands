<?php

declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Composer\Console\Input\InputOption;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ThemeCreateCommand extends Command
{
    public function __construct(
        private DirectoryList $directoryList,
        private ComponentRegistrar $componentRegistrar,
        private string $themeSkeletonFolder = '',
        string $name = null
    ) {
        parent::__construct($name);
    }
    
    /**
     * Initialization of the command.
     */
    protected function configure(): void
    {
        $this->setName('theme:create');
        $this->setDescription('Create a new theme in app/design/');
        $this->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme name (example: Yireo/foobar');
        $this->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent theme name (example: Magento/luma',
            'Parent theme name (example: Yireo/foobar');
        $this->addOption('application', null, InputOption::VALUE_OPTIONAL, 'Application');
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
        $themeName = trim($input->getOption('theme'));
        $parentThemeName = trim($input->getOption('parent'));
        $application = trim($input->getOption('application'));
        
        $themeFolder = $this->getThemeFolder($themeName, $application);
        exec('mkdir -p '.$themeFolder);
        
        $this->generateRegistrationFile($themeFolder, $application . '/' . $themeName);
        $this->generateThemeXmlFile($themeFolder, $themeName, $parentThemeName);
        return Command::SUCCESS;
    }
    
    /**
     * @param string $themeFolder
     * @param string $componentName
     * @return void
     */
    private function generateRegistrationFile(string $themeFolder, string $componentName)
    {
        $themeSkeletonFolder = $this->getThemeSkeletonFolder();
        $contents = file_get_contents($themeSkeletonFolder . '/registration.php.tmpl');
        $contents = str_replace('{{ COMPONENT_NAME }}', $componentName, $contents);
        file_put_contents($themeFolder . '/registration.php', $contents);
    }
    
    /**
     * @param string $themeFolder
     * @param string $componentName
     * @return void
     */
    private function generateThemeXmlFile(string $themeFolder, string $themeName, string $parentThemeName)
    {
        $themeSkeletonFolder = $this->getThemeSkeletonFolder();
        $contents = file_get_contents($themeSkeletonFolder . '/theme.xml.tmpl');
        $contents = str_replace('{{ THEME_NAME }}', $themeName, $contents);
        $contents = str_replace('{{ PARENT_THEME_NAME }}', $parentThemeName, $contents);
        file_put_contents($themeFolder . '/theme.xml', $contents);
    }
    
    /**
     * @param string $themeName
     * @param string $application
     * @return string
     */
    private function getThemeFolder(string $themeName, string $application): string
    {
        if (empty($application)) {
            $application = 'frontend';
        }
        
        return $this->directoryList->getRoot() . '/app/design/' . $application . '/' . $themeName;
    }
    
    /**
     * @return string
     */
    private function getThemeSkeletonFolder(): string
    {
        $moduleFolder = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Yireo_ThemeCommands');
        $defaultThemeSkeletonFolder = $moduleFolder . '/files/';
        if (empty($this->themeSkeletonFolder)) {
            return $defaultThemeSkeletonFolder;
        }
        
        $themeSkeletonFolder = $this->directoryList->getRoot() . '/' . $this->themeSkeletonFolder;
        if (!is_dir($this->themeSkeletonFolder)) {
            return $defaultThemeSkeletonFolder;
        }
        
        return $themeSkeletonFolder;
    }
}
