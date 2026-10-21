<?php
declare(strict_types=1);

namespace App\Infrastructure\Shared\Utils;

final class AppPath
{
    public static function getRoot(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $projectRoot = rtrim(dirname(dirname($scriptName)), '/\\');
        if ($projectRoot === '/' || $projectRoot === '') {
            return '';
        }
        return $projectRoot;
    }

    public static function url(string $path): string
    {
        $root = self::getRoot();
        return $root . '/' . ltrim($path, '/');
    }
}
