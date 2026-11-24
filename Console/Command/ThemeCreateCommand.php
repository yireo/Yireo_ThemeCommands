<?php

declare(strict_types=1);

namespace Yireo\ThemeCommands\Console\Command;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Yireo\ThemeCommands\Console\OutputSanitizer;

class ThemeCreateCommand extends Command
{
    public const THEME_NAME_PATTERN = '#^[A-Z][A-Za-z0-9]*/[a-z0-9_-]+$#';
    public const PARENT_THEME_NAME_PATTERN = '#^[A-Za-z0-9_-]+/[A-Za-z0-9_-]+$#';
    public const APPLICATIONS = ['frontend', 'adminhtml'];

    public function __construct(
        private DirectoryList $directoryList,
        private ComponentRegistrar $componentRegistrar,
        private File $fileDriver,
        private ThemeCollectionFactory $themeCollectionFactory,
        private string $themeSkeletonFolder = '',
        ?string $name = null
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
        $this->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme name (example: Yireo/foobar)');
        $this->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent theme name (example: Magento/luma)');
        $this->addOption('application', null, InputOption::VALUE_OPTIONAL, 'Application (frontend or adminhtml)');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing theme files');
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
        $themeName = trim((string)$input->getOption('theme'), " \f\n\r\t\v\x00");
        $parentThemeName = trim((string)$input->getOption('parent'), " \f\n\r\t\v\x00");
        $application = trim((string)$input->getOption('application'), " \f\n\r\t\v\x00");

        if (empty($application)) {
            $application = 'frontend';
        }

        if (empty($parentThemeName)) {
            $parentThemeName = $application === 'adminhtml' ? 'Magento/backend' : 'Magento/luma';
        }

        if (!in_array($application, self::APPLICATIONS, true)) {
            $output->writeln('<error>Wrong application: ' . OutputSanitizer::sanitize($application)
                . '. Can only be: ' . implode(', ', self::APPLICATIONS) . '</error>');
            return Command::FAILURE;
        }

        if (!preg_match(self::THEME_NAME_PATTERN, $themeName)) {
            $output->writeln('<error>Wrong theme name: ' . OutputSanitizer::sanitize($themeName)
                . ' (example: Yireo/foobar)</error>');
            return Command::FAILURE;
        }

        if (!preg_match(self::PARENT_THEME_NAME_PATTERN, $parentThemeName)
            || !$this->themeExists($application . '/' . $parentThemeName)) {
            $output->writeln('<error>Wrong parent theme name: ' . OutputSanitizer::sanitize($parentThemeName)
                . '</error>');
            return Command::FAILURE;
        }

        $themeFolder = $this->getThemeFolder($themeName, $application);
        $registrationFile = $themeFolder . '/registration.php';
        $themeXmlFile = $themeFolder . '/theme.xml';
        $force = (bool)$input->getOption('force');
        if (!$force && ($this->fileDriver->isExists($registrationFile) || $this->fileDriver->isExists($themeXmlFile))) {
            $output->writeln('<error>Theme files already exist in ' . OutputSanitizer::sanitize($themeFolder)
                . '. Use --force to overwrite them.</error>');
            return Command::FAILURE;
        }

        $this->fileDriver->createDirectory($themeFolder);
        $this->generateRegistrationFile($registrationFile, $application . '/' . $themeName);
        $this->generateThemeXmlFile($themeXmlFile, $themeName, $parentThemeName);

        $output->writeln('Theme created in ' . OutputSanitizer::sanitize($themeFolder));
        return Command::SUCCESS;
    }

    private function themeExists(string $fullPath): bool
    {
        return (bool)$this->themeCollectionFactory->create()->getThemeByFullPath($fullPath)->getId();
    }

    private function generateRegistrationFile(string $file, string $componentName): void
    {
        $contents = $this->getTemplateContents('registration.php.tmpl');
        $contents = str_replace('{{ COMPONENT_NAME }}', addcslashes($componentName, "'\\"), $contents);
        $this->fileDriver->filePutContents($file, $contents);
    }

    private function generateThemeXmlFile(string $file, string $themeName, string $parentThemeName): void
    {
        $contents = $this->getTemplateContents('theme.xml.tmpl');
        $contents = str_replace('{{ THEME_NAME }}', $this->escapeXml($themeName), $contents);
        $contents = str_replace('{{ PARENT_THEME_NAME }}', $this->escapeXml($parentThemeName), $contents);
        $this->fileDriver->filePutContents($file, $contents);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @throws FileSystemException
     */
    private function getTemplateContents(string $templateFile): string
    {
        $file = $this->getThemeSkeletonFolder() . '/' . $templateFile;
        if (!$this->fileDriver->isFile($file)) {
            throw new FileSystemException(__('Template file "%1" not found', $file));
        }

        return $this->fileDriver->fileGetContents($file);
    }

    private function getThemeFolder(string $themeName, string $application): string
    {
        return $this->directoryList->getRoot() . '/app/design/' . $application . '/' . $themeName;
    }

    private function getThemeSkeletonFolder(): string
    {
        $moduleFolder = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Yireo_ThemeCommands');
        $defaultThemeSkeletonFolder = $moduleFolder . '/files';
        if (empty($this->themeSkeletonFolder)) {
            return $defaultThemeSkeletonFolder;
        }

        $root = $this->fileDriver->getRealPath($this->directoryList->getRoot());
        $themeSkeletonFolder = $this->fileDriver->getRealPath(
            $this->directoryList->getRoot() . '/' . $this->themeSkeletonFolder
        );

        if (!is_string($root) || !is_string($themeSkeletonFolder)
            || !str_starts_with($themeSkeletonFolder, $root . '/')
            || !$this->fileDriver->isDirectory($themeSkeletonFolder)) {
            return $defaultThemeSkeletonFolder;
        }

        return $themeSkeletonFolder;
    }
}
