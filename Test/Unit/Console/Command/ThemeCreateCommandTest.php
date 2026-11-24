<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Unit\Console\Command;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Theme\Model\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ThemeCommands\Console\Command\ThemeCreateCommand;

class ThemeCreateCommandTest extends TestCase
{
    private string $root;
    private array $existingThemes = ['frontend/Magento/luma', 'frontend/Hyva/default-csp', 'adminhtml/Magento/backend'];

    #[DataProvider('getValidThemeNames')]
    public function testThemeNamePatternAcceptsValidNames(string $themeName): void
    {
        $this->assertMatchesRegularExpression(ThemeCreateCommand::THEME_NAME_PATTERN, $themeName);
    }

    public static function getValidThemeNames(): array
    {
        return [['Yireo/foobar'], ['Yireo/foo_bar'], ['Yireo/foo-bar2'], ['MyVendor2/default']];
    }

    #[DataProvider('getInvalidThemeNames')]
    public function testInvalidThemeNameIsRejected(string $themeName): void
    {
        $tester = $this->execute(['--theme' => $themeName]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Wrong theme name', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->root . '/app/design');
    }

    public static function getInvalidThemeNames(): array
    {
        return [
            'empty' => [''],
            'no vendor' => ['foobar'],
            'command injection' => ['Yireo/foo;touch /tmp/pwned'],
            'subshell' => ['Yireo/$(id)'],
            'php injection' => ["Yireo/x'.system('id').'"],
            'path traversal' => ['Yireo/../../../pub/x'],
            'unanchored match' => ['../../Yireo/foo'],
            'extra segment' => ['Yireo/foo/bar'],
            'lowercase vendor' => ['yireo/foobar'],
            'uppercase theme' => ['Yireo/Foobar'],
            'newline' => ["Yireo/foo\nbar"],
            'xml injection' => ['Yireo/foo</title>'],
        ];
    }

    #[DataProvider('getInvalidApplications')]
    public function testInvalidApplicationIsRejected(string $application): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/foobar', '--application' => $application]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Wrong application', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->root . '/app/design');
    }

    public static function getInvalidApplications(): array
    {
        return [
            'path traversal' => ['../code'],
            'command injection' => ['frontend;id>/tmp/x;'],
            'unknown area' => ['base'],
        ];
    }

    #[DataProvider('getInvalidParentThemeNames')]
    public function testInvalidParentThemeIsRejected(string $parentThemeName): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/foobar', '--parent' => $parentThemeName]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Wrong parent theme name', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->root . '/app/design');
    }

    public static function getInvalidParentThemeNames(): array
    {
        return [
            'xml injection' => ['Magento/luma</parent><media>'],
            'not existing' => ['Yireo/unknown'],
            'other area' => ['Magento/backend'],
        ];
    }

    public function testThemeIsCreated(): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/foo-bar', '--parent' => 'Hyva/default-csp']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $themeFolder = $this->root . '/app/design/frontend/Yireo/foo-bar';

        $registration = (string)file_get_contents($themeFolder . '/registration.php');
        $this->assertStringContainsString("'frontend/Yireo/foo-bar'", $registration);
        $this->assertSame([T_CONSTANT_ENCAPSED_STRING], $this->getStringTokenTypes($registration));

        $themeXml = simplexml_load_file($themeFolder . '/theme.xml');
        $this->assertNotFalse($themeXml);
        $this->assertSame('Yireo/foo-bar', (string)$themeXml->title);
        $this->assertSame('Hyva/default-csp', (string)$themeXml->parent);
    }

    public function testAdminhtmlThemeDefaultsToBackendParent(): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/admin', '--application' => 'adminhtml']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $themeXml = simplexml_load_file($this->root . '/app/design/adminhtml/Yireo/admin/theme.xml');
        $this->assertNotFalse($themeXml);
        $this->assertSame('Magento/backend', (string)$themeXml->parent);
    }

    public function testExistingThemeIsNotOverwrittenWithoutForce(): void
    {
        $themeFolder = $this->root . '/app/design/frontend/Yireo/foobar';
        mkdir($themeFolder, 0777, true);
        file_put_contents($themeFolder . '/registration.php', 'original');

        $tester = $this->execute(['--theme' => 'Yireo/foobar']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Use --force', $tester->getDisplay());
        $this->assertSame('original', file_get_contents($themeFolder . '/registration.php'));
        $this->assertFileDoesNotExist($themeFolder . '/theme.xml');
    }

    public function testExistingThemeIsOverwrittenWithForce(): void
    {
        $themeFolder = $this->root . '/app/design/frontend/Yireo/foobar';
        mkdir($themeFolder, 0777, true);
        file_put_contents($themeFolder . '/theme.xml', 'original');

        $tester = $this->execute(['--theme' => 'Yireo/foobar', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('<parent>Magento/luma</parent>', (string)file_get_contents($themeFolder . '/theme.xml'));
    }

    public function testCustomSkeletonFolderOutsideRootIsIgnored(): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/foobar'], '../../../../etc');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString(
            'ComponentRegistrar::THEME',
            (string)file_get_contents($this->root . '/app/design/frontend/Yireo/foobar/registration.php')
        );
    }

    public function testCustomSkeletonFolderInsideRootIsUsed(): void
    {
        mkdir($this->root . '/skeleton');
        file_put_contents($this->root . '/skeleton/registration.php.tmpl', "custom '{{ COMPONENT_NAME }}'");
        file_put_contents($this->root . '/skeleton/theme.xml.tmpl', 'custom {{ THEME_NAME }}');

        $tester = $this->execute(['--theme' => 'Yireo/foobar'], 'skeleton');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $themeFolder = $this->root . '/app/design/frontend/Yireo/foobar';
        $this->assertSame("custom 'frontend/Yireo/foobar'", file_get_contents($themeFolder . '/registration.php'));
        $this->assertSame('custom Yireo/foobar', file_get_contents($themeFolder . '/theme.xml'));
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yireo-theme-commands-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    private function execute(array $options, string $themeSkeletonFolder = ''): CommandTester
    {
        $componentRegistrar = $this->createMock(ComponentRegistrar::class);
        $componentRegistrar->method('getPath')->willReturn(dirname(__DIR__, 4));

        $themeCollection = $this->createMock(ThemeCollection::class);
        $themeCollection->method('getThemeByFullPath')->willReturnCallback(function (string $fullPath) {
            $theme = $this->createMock(Theme::class);
            $theme->method('getId')->willReturn(in_array($fullPath, $this->existingThemes, true) ? 1 : null);
            return $theme;
        });
        $themeCollectionFactory = $this->createMock(ThemeCollectionFactory::class);
        $themeCollectionFactory->method('create')->willReturn($themeCollection);

        $command = new ThemeCreateCommand(
            new DirectoryList($this->root),
            $componentRegistrar,
            new File(),
            $themeCollectionFactory,
            $themeSkeletonFolder
        );

        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }

    private function getStringTokenTypes(string $code): array
    {
        $types = [];
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $types[] = $token[0];
            }
        }

        return $types;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (array_diff((array)scandir($directory), ['.', '..']) as $item) {
            $path = $directory . '/' . $item;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
