<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Unit\Console\Command;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Store\Model\ResourceModel\Store as StoreResourceModel;
use Magento\Store\Model\ResourceModel\Website as WebsiteResourceModel;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\Website;
use Magento\Store\Model\WebsiteFactory;
use Magento\Theme\Model\ResourceModel\Theme as ThemeResourceModel;
use Magento\Theme\Model\Theme;
use Magento\Theme\Model\ThemeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ThemeCommands\Console\Command\ThemeChangeCommand;

class ThemeChangeCommandTest extends TestCase
{
    private ConfigInterface&MockObject $config;
    private ResourceConnection&MockObject $resourceConnection;
    private ?int $themeId = 3;
    private ?int $websiteId = 2;
    private ?int $storeId = 5;

    public function testWebsitesScopeWithNumericId(): void
    {
        $this->config->expects($this->once())
            ->method('saveConfig')
            ->with('design/theme/theme_id', 3, 'websites', '2');

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => '2']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Saving 3 for scope websites 2', $tester->getDisplay());
    }

    public function testWebsitesScopeWithWebsiteCode(): void
    {
        $this->config->expects($this->once())
            ->method('saveConfig')
            ->with('design/theme/theme_id', 3, 'websites', 2);

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => 'base']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testWebsitesScopeWithUnknownWebsiteCode(): void
    {
        $this->websiteId = null;
        $this->config->expects($this->never())->method('saveConfig');

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => 'unknown']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not a valid scope_id', $tester->getDisplay());
    }

    public function testSingularWebsiteScopeIsRejected(): void
    {
        $this->config->expects($this->never())->method('saveConfig');

        $tester = $this->execute(['scope' => 'website', 'scope_id' => '2']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(
            'Not a valid scope. Can only be: default, websites, stores',
            $tester->getDisplay()
        );
    }

    public function testStoresScopeWithStoreCode(): void
    {
        $this->config->expects($this->once())
            ->method('saveConfig')
            ->with('design/theme/theme_id', 3, 'stores', 5);

        $tester = $this->execute(['scope' => 'stores', 'scope_id' => 'default']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testScopeIdWithoutScopeDefaultsToStores(): void
    {
        $this->config->expects($this->once())
            ->method('saveConfig')
            ->with('design/theme/theme_id', 3, 'stores', '1');

        $tester = $this->execute(['scope_id' => '1']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testNoScopeDefaultsToDefaultScope(): void
    {
        $this->config->expects($this->once())
            ->method('saveConfig')
            ->with('design/theme/theme_id', 3, 'default', 0);

        $tester = $this->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResetDeletesThemeConfigOfOtherScopes(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('delete')
            ->with('core_config_data', [
                'path = ?' => 'design/theme/theme_id',
                'scope != ?' => 'default',
            ]);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->willReturn('core_config_data');

        $tester = $this->execute(['--reset' => null]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testWithoutResetNothingIsDeleted(): void
    {
        $this->resourceConnection->expects($this->never())->method('getConnection');

        $tester = $this->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testUnknownThemeIsRejected(): void
    {
        $this->themeId = null;
        $this->config->expects($this->never())->method('saveConfig');

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => '2']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not a valid theme: Magento/luma', $tester->getDisplay());
    }

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
    }

    private function execute(array $arguments): CommandTester
    {
        $theme = $this->createMock(Theme::class);
        $theme->method('getId')->willReturn($this->themeId);
        $themeFactory = $this->createMock(ThemeFactory::class);
        $themeFactory->method('create')->willReturn($theme);

        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn($this->websiteId);
        $websiteFactory = $this->createMock(WebsiteFactory::class);
        $websiteFactory->method('create')->willReturn($website);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($this->storeId);
        $storeFactory = $this->createMock(StoreFactory::class);
        $storeFactory->method('create')->willReturn($store);

        $indexerFactory = $this->createMock(IndexerFactory::class);
        $indexerFactory->method('create')->willReturn($this->createMock(Indexer::class));

        $command = new ThemeChangeCommand(
            $this->config,
            $this->createMock(CacheManager::class),
            $indexerFactory,
            $this->createMock(ThemeResourceModel::class),
            $themeFactory,
            $this->createMock(WebsiteResourceModel::class),
            $websiteFactory,
            $this->createMock(StoreResourceModel::class),
            $storeFactory,
            $this->resourceConnection
        );

        $tester = new CommandTester($command);
        $tester->execute(array_merge(['theme_name' => 'Magento/luma'], $arguments));

        return $tester;
    }
}
