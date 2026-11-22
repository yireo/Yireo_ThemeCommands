<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Integration\Console\Command;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\CommandListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ThemeCommands\Console\Command\ThemeChangeCommand;
use Yireo\ThemeCommands\Console\Command\ThemeDesignConfigCommand;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ThemeChangeCommandTest extends TestCase
{
    private int $websiteId;

    public function testCommandsAreRegistered(): void
    {
        $commandList = Bootstrap::getObjectManager()->get(CommandListInterface::class);
        $commands = $commandList->getCommands();

        $this->assertArrayHasKey('yireo_themecommands_theme_change_command', $commands);
        $this->assertInstanceOf(ThemeChangeCommand::class, $commands['yireo_themecommands_theme_change_command']);
        $this->assertArrayHasKey('yireo_themecommands_theme_design_config_command', $commands);
        $this->assertInstanceOf(
            ThemeDesignConfigCommand::class,
            $commands['yireo_themecommands_theme_design_config_command']
        );
    }

    public function testThemeChangeSavesThemeForWebsitesScopeById(): void
    {
        $tester = $this->executeThemeChange((string)$this->websiteId);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([$this->getLumaThemeId()], $this->getSavedThemeIds('websites', $this->websiteId));
    }

    public function testThemeChangeSavesThemeForWebsitesScopeByCode(): void
    {
        $tester = $this->executeThemeChange('base');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([$this->getLumaThemeId()], $this->getSavedThemeIds('websites', $this->websiteId));
    }

    public function testThemeChangeRejectsSingularWebsiteScope(): void
    {
        $tester = new CommandTester(Bootstrap::getObjectManager()->create(ThemeChangeCommand::class));
        $tester->execute(['theme_name' => 'Magento/luma', 'scope' => 'website', 'scope_id' => (string)$this->websiteId]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame([], $this->getSavedThemeIds('website', $this->websiteId));
    }

    public function testDesignConfigShowsNameBeforeId(): void
    {
        $website = Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getWebsite($this->websiteId);

        $tester = new CommandTester(Bootstrap::getObjectManager()->create(ThemeDesignConfigCommand::class));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertMatchesRegularExpression(
            '/\|\s*' . preg_quote((string)$website->getName(), '/') . '\s*\|\s*' . $this->websiteId . '\s*\|/',
            $tester->getDisplay()
        );
    }

    protected function setUp(): void
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        $this->websiteId = (int)$storeManager->getWebsite('base')->getId();
        $this->deleteThemeConfig();
    }

    protected function tearDown(): void
    {
        $this->deleteThemeConfig();
    }

    private function executeThemeChange(string $scopeId): CommandTester
    {
        $tester = new CommandTester(Bootstrap::getObjectManager()->create(ThemeChangeCommand::class));
        $tester->execute(['theme_name' => 'Magento/luma', 'scope' => 'websites', 'scope_id' => $scopeId]);

        return $tester;
    }

    private function getSavedThemeIds(string $scope, int $scopeId): array
    {
        $resourceConnection = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $select = $connection->select()
            ->from($resourceConnection->getTableName('core_config_data'), ['value'])
            ->where('path = ?', 'design/theme/theme_id')
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId);

        return array_map('intval', $connection->fetchCol($select));
    }

    private function getLumaThemeId(): int
    {
        $resourceConnection = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $select = $connection->select()
            ->from($resourceConnection->getTableName('theme'), ['theme_id'])
            ->where('theme_path = ?', 'Magento/luma')
            ->where('area = ?', 'frontend');

        return (int)$connection->fetchOne($select);
    }

    private function deleteThemeConfig(): void
    {
        $config = Bootstrap::getObjectManager()->get(ConfigInterface::class);
        $config->deleteConfig('design/theme/theme_id', 'websites', $this->websiteId);
        $config->deleteConfig('design/theme/theme_id', 'website', $this->websiteId);
    }
}
