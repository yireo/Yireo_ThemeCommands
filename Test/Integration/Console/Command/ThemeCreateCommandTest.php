<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Integration\Console\Command;

use Magento\Framework\Console\CommandListInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ThemeCommands\Console\Command\ThemeCreateCommand;

/**
 * @magentoAppIsolation enabled
 */
class ThemeCreateCommandTest extends TestCase
{
    private string $root;

    public function testCommandIsRegistered(): void
    {
        $commands = Bootstrap::getObjectManager()->get(CommandListInterface::class)->getCommands();

        $this->assertArrayHasKey('yireo_themecommands_theme_create_command', $commands);
        $this->assertInstanceOf(ThemeCreateCommand::class, $commands['yireo_themecommands_theme_create_command']);
    }

    public function testThemeIsCreatedWithExistingParent(): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/example', '--parent' => 'Magento/luma']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $themeFolder = $this->root . '/app/design/frontend/Yireo/example';
        $this->assertStringContainsString(
            "'frontend/Yireo/example'",
            (string)file_get_contents($themeFolder . '/registration.php')
        );
        $themeXml = simplexml_load_file($themeFolder . '/theme.xml');
        $this->assertNotFalse($themeXml);
        $this->assertSame('Magento/luma', (string)$themeXml->parent);
    }

    public function testUnknownParentThemeIsRejected(): void
    {
        $tester = $this->execute(['--theme' => 'Yireo/example', '--parent' => 'Yireo/doesnotexist']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->root . '/app/design');
    }

    public function testMaliciousInputIsRejectedWithoutSideEffects(): void
    {
        $marker = $this->root . '/pwned';
        $tester = $this->execute(['--theme' => 'Yireo/foo;touch ' . $marker]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());

        $tester = $this->execute(['--theme' => 'Yireo/foo', '--application' => '../code']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());

        $this->assertFileDoesNotExist($marker);
        $this->assertDirectoryDoesNotExist($this->root . '/app');
    }

    public function testExistingThemeRequiresForce(): void
    {
        $this->assertSame(Command::SUCCESS, $this->execute(['--theme' => 'Yireo/example'])->getStatusCode());

        $tester = $this->execute(['--theme' => 'Yireo/example']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Use --force', $tester->getDisplay());

        $tester = $this->execute(['--theme' => 'Yireo/example', '--force' => true]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
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

    private function execute(array $options): CommandTester
    {
        $command = Bootstrap::getObjectManager()->create(ThemeCreateCommand::class, [
            'directoryList' => new DirectoryList($this->root),
        ]);

        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
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
