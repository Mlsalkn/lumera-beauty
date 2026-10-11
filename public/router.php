<?php
declare(strict_types=1);
// PHP development server: expose only files inside public, never project source.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$resolved = realpath(__DIR__ . $path);
if ($resolved && str_starts_with($resolved, __DIR__ . DIRECTORY_SEPARATOR) && is_file($resolved) && !str_contains($path, '/.') && str_starts_with($path, '/assets/')) { return false; }
require __DIR__ . '/index.php';
