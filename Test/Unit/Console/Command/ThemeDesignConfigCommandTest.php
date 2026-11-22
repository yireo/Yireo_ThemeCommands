<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Unit\Console\Command;

use ArrayIterator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreRepository;
use Magento\Store\Model\Website;
use Magento\Store\Model\WebsiteRepository;
use Magento\Theme\Model\ResourceModel\Design\Config\Scope\Collection;
use Magento\Theme\Model\ResourceModel\Design\Config\Scope\CollectionFactory;
use Magento\Theme\Model\Theme;
use Magento\Theme\Model\Theme\ThemeProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ThemeCommands\Console\Command\ThemeDesignConfigCommand;

class ThemeDesignConfigCommandTest extends TestCase
{
    public function testRowValuesMatchHeaderOrder(): void
    {
        $designConfig = new DataObject([
            'store_website_id' => 7,
            'store_id' => 8,
            'theme_theme_id' => 9,
        ]);

        $collection = $this->createMock(Collection::class);
        $collection->method('getIterator')->willReturn(new ArrayIterator([$designConfig]));
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn(7);
        $website->method('getName')->willReturn('Example Website');
        $websiteRepository = $this->createMock(WebsiteRepository::class);
        $websiteRepository->method('get')->with(7)->willReturn($website);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(8);
        $store->method('getName')->willReturn('Example Store View');
        $storeRepository = $this->createMock(StoreRepository::class);
        $storeRepository->method('getById')->with(8)->willReturn($store);

        $theme = $this->createMock(Theme::class);
        $theme->method('getId')->willReturn(9);
        $theme->method('getCode')->willReturn('Example/theme');
        $themeProvider = $this->createMock(ThemeProvider::class);
        $themeProvider->method('getThemeById')->with(9)->willReturn($theme);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('fetchCol')->willReturn(['9']);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturn('core_config_data');

        $command = new ThemeDesignConfigCommand(
            $collectionFactory,
            $websiteRepository,
            $storeRepository,
            $themeProvider,
            $resourceConnection
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertMatchesRegularExpression(
            '/\|\s*Website\s*\|\s*Website ID\s*\|\s*Store View\s*\|\s*Store View ID\s*\|\s*Theme\s*\|\s*Theme ID\s*\|\s*Override\s*\|/',
            $display
        );
        $this->assertMatchesRegularExpression(
            '/\|\s*Example Website\s*\|\s*7\s*\|\s*Example Store View\s*\|\s*8\s*\|\s*Example\/theme\s*\|\s*9\s*\|\s*x\s*\|/',
            $display
        );
    }
}
