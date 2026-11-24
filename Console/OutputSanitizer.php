<?php declare(strict_types=1);

namespace Yireo\ThemeCommands\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;

class OutputSanitizer
{
    public static function sanitize(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string)preg_replace('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/', '', (string)$value);

        return OutputFormatter::escape($value);
    }
}
