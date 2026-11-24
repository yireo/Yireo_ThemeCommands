<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Test\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Yireo\ThemeCommands\Console\OutputSanitizer;

class OutputSanitizerTest extends TestCase
{
    #[DataProvider('getValues')]
    public function testSanitize(mixed $value, string $expected): void
    {
        $this->assertSame($expected, OutputSanitizer::sanitize($value));
    }

    public static function getValues(): array
    {
        return [
            'plain text' => ['Magento/luma', 'Magento/luma'],
            'integer' => [42, '42'],
            'null' => [null, ''],
            'array' => [['x'], ''],
            'formatter tags' => ['</error><fg=red>x', '\\</error\\>\\<fg=red\\>x'],
            'ansi escape' => ["a\e[2Jb", 'a[2Jb'],
            'osc 8 hyperlink' => ["\e]8;;https://example.com\e\\link", ']8;;https://example.com\\link'],
            'newline and tab' => ["a\nb\tc\r", 'abc'],
            'c1 control' => ["a\u{9B}2Jb", 'a2Jb'],
            'utf8 text' => ['Thème été', 'Thème été'],
        ];
    }

    public function testSanitizedTagsAreRenderedLiterally(): void
    {
        $formatter = new OutputFormatter(true);
        $this->assertSame('<error>x</error>', $formatter->format(OutputSanitizer::sanitize('<error>x</error>')));
    }
}
