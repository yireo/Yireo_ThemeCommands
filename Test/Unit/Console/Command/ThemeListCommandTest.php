<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Unit\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Theme\Model\Theme;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\ThemeCommands\Console\Command\ThemeListCommand;

class ThemeListCommandTest extends TestCase
{
    public function testCircularParentThemesDoNotRecurseEndlessly(): void
    {
        $themeA = $this->createTheme(1, 'Yireo/a', 'Theme A');
        $themeB = $this->createTheme(2, 'Yireo/b', 'Theme B');
        $themeA->method('getParentTheme')->willReturn($themeB);
        $themeB->method('getParentTheme')->willReturn($themeA);

        $tester = $this->execute([$themeA, $themeB], ['--json' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame(['unknown', 'unknown'], array_column($data, 'type'));
    }

    public function testThemeTypeIsInheritedFromParent(): void
    {
        $parent = $this->createTheme(1, 'Hyva/default', 'Hyva');
        $child = $this->createTheme(2, 'Yireo/child', 'Child');
        $child->method('getParentTheme')->willReturn($parent);

        $tester = $this->execute([$child], ['--type' => 'hyva']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Yireo/child', $tester->getDisplay());
    }

    public function testThemeTitleIsEscapedInTable(): void
    {
        $theme = $this->createTheme(1, 'Magento/luma', "<fg=red>Evil\e[2J</>");

        $tester = $this->execute([$theme], []);

        $this->assertStringContainsString("<fg=red>Evil[2J</>", $tester->getDisplay());
        $this->assertStringNotContainsString("\e", $tester->getDisplay());
    }

    private function createTheme(int $id, string $path, string $title): Theme&MockObject
    {
        $theme = $this->createMock(Theme::class);
        $theme->method('getId')->willReturn($id);
        $theme->method('getThemePath')->willReturn($path);
        $theme->method('getArea')->willReturn('frontend');
        $theme->method('__call')->willReturnCallback(
            fn(string $method) => $method === 'getThemeTitle' ? $title : null
        );

        return $theme;
    }

    private function execute(array $themes, array $options): CommandTester
    {
        $collection = $this->createMock(ThemeCollection::class);
        $collection->method('getItems')->willReturn($themes);
        $collectionFactory = $this->createMock(ThemeCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([]);

        $command = new ThemeListCommand(
            $collectionFactory,
            $this->createMock(ScopeConfigInterface::class),
            $storeManager
        );

        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }
}
