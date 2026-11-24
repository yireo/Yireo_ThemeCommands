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
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Theme\Model\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
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
    private array $loadedFields = [];
    private ?string $requestedThemePath = null;
    private ?ThemeChangeCommand $command = null;

    public function testWebsitesScopeWithNumericId(): void
    {
        $this->config->expects($this->once())
            ->method('saveConfig')
            ->with('design/theme/theme_id', 3, 'websites', 2);

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => '2']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Saving 3 for scope websites 2', $tester->getDisplay());
        $this->assertSame([['website', '2', null]], $this->loadedFields);
    }

    public function testWebsitesScopeWithUnknownNumericIdIsRejected(): void
    {
        $this->websiteId = null;
        $this->config->expects($this->never())->method('saveConfig');

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => '99999']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not a valid scope_id', $tester->getDisplay());
    }

    #[DataProvider('getInvalidNumericScopeIds')]
    public function testNonDecimalNumericScopeIdIsTreatedAsCode(string $scopeId): void
    {
        $this->storeId = null;
        $this->config->expects($this->never())->method('saveConfig');

        $tester = $this->execute(['scope' => 'stores', 'scope_id' => $scopeId]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame([['store', $scopeId, 'code']], $this->loadedFields);
    }

    public static function getInvalidNumericScopeIds(): array
    {
        return [['1e3'], ['-1'], ['1.5'], ['0x1A']];
    }

    public function testDefaultScopeWithNonZeroScopeIdIsRejected(): void
    {
        $this->config->expects($this->never())->method('saveConfig');

        $tester = $this->execute(['scope' => 'default', 'scope_id' => '5']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not a valid scope_id', $tester->getDisplay());
    }

    public function testThemeIsLookedUpInFrontendArea(): void
    {
        $this->execute([]);
        $this->assertSame('frontend/Magento/luma', $this->requestedThemePath);
    }

    public function testInvalidScopeIsRejectedBeforeLookingUpScopeId(): void
    {
        $tester = $this->execute(['scope' => 'groups', 'scope_id' => 'base']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame([], $this->loadedFields);
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
            ->with('design/theme/theme_id', 3, 'stores', 5);

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
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())
            ->method('delete')
            ->with('core_config_data', [
                'path = ?' => 'design/theme/theme_id',
                'scope != ?' => 'default',
            ]);
        $connection->expects($this->once())->method('commit');
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->willReturn('core_config_data');

        $tester = $this->execute(['--reset' => true], ['interactive' => false]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResetIsRolledBackWhenDeleteFails(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('delete')->willThrowException(new \RuntimeException('Delete failed'));
        $connection->expects($this->once())->method('rollBack');
        $connection->expects($this->never())->method('commit');
        $this->resourceConnection->method('getConnection')->willReturn($connection);

        $this->expectException(\RuntimeException::class);
        $this->execute(['--reset' => true], ['interactive' => false]);
    }

    public function testResetAsksForConfirmation(): void
    {
        $this->config->expects($this->never())->method('saveConfig');
        $this->resourceConnection->expects($this->never())->method('getConnection');

        $tester = $this->execute(['--reset' => true], ['interactive' => true], ['n']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Aborted', $tester->getDisplay());
    }

    public function testResetIsDoneAfterConfirmation(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete');
        $this->resourceConnection->method('getConnection')->willReturn($connection);

        $tester = $this->execute(['--reset' => true], ['interactive' => true], ['y']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResetWithOtherScopeIsRejected(): void
    {
        $this->config->expects($this->never())->method('saveConfig');
        $this->resourceConnection->expects($this->never())->method('getConnection');

        $tester = $this->execute(['scope' => 'websites', 'scope_id' => '2', '--reset' => true]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('--reset can only be used with the default scope', $tester->getDisplay());
    }

    public function testResetDoesNotAcceptAValue(): void
    {
        $tester = $this->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFalse($this->command->getDefinition()->getOption('reset')->acceptValue());
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

    public function testInvalidThemeNameIsEscapedInOutput(): void
    {
        $this->themeId = null;

        $tester = $this->execute(['theme_name' => "</error><info>x\e[2J"]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not a valid theme: </error><info>x[2J', $tester->getDisplay());
    }

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
    }

    private function execute(array $arguments, array $options = [], array $inputs = []): CommandTester
    {
        $theme = $this->createMock(Theme::class);
        $theme->method('getId')->willReturn($this->themeId);
        $themeCollection = $this->createMock(ThemeCollection::class);
        $themeCollection->method('getThemeByFullPath')->willReturnCallback(
            function (string $fullPath) use ($theme) {
                $this->requestedThemePath = $fullPath;
                return $theme;
            }
        );
        $themeCollectionFactory = $this->createMock(ThemeCollectionFactory::class);
        $themeCollectionFactory->method('create')->willReturn($themeCollection);

        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn($this->websiteId);
        $websiteFactory = $this->createMock(WebsiteFactory::class);
        $websiteFactory->method('create')->willReturn($website);
        $websiteResourceModel = $this->createMock(WebsiteResourceModel::class);
        $websiteResourceModel->method('load')->willReturnCallback(
            function ($object, $value, $field = null) use ($websiteResourceModel) {
                $this->loadedFields[] = ['website', $value, $field];
                return $websiteResourceModel;
            }
        );

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($this->storeId);
        $storeFactory = $this->createMock(StoreFactory::class);
        $storeFactory->method('create')->willReturn($store);
        $storeResourceModel = $this->createMock(StoreResourceModel::class);
        $storeResourceModel->method('load')->willReturnCallback(
            function ($object, $value, $field = null) use ($storeResourceModel) {
                $this->loadedFields[] = ['store', $value, $field];
                return $storeResourceModel;
            }
        );

        $indexerFactory = $this->createMock(IndexerFactory::class);
        $indexerFactory->method('create')->willReturn($this->createMock(Indexer::class));

        $this->command = $command = new ThemeChangeCommand(
            $this->config,
            $this->createMock(CacheManager::class),
            $indexerFactory,
            $themeCollectionFactory,
            $websiteResourceModel,
            $websiteFactory,
            $storeResourceModel,
            $storeFactory,
            $this->resourceConnection
        );

        $tester = new CommandTester($command);
        $tester->setInputs($inputs);
        $tester->execute(array_merge(['theme_name' => 'Magento/luma'], $arguments), $options);

        return $tester;
    }
}
