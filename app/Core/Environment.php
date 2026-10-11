<?php
declare(strict_types=1);
namespace App\Core;
final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) { return; }
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) { continue; }
            if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $match)) {
                throw new \RuntimeException('Geçersiz environment satırı.');
            }
            $value = trim($match[2]);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($match[1]) === false) { putenv($match[1] . '=' . $value); }
        }
    }
}
