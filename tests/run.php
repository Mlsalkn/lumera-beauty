<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
ob_start(); // Keep CLI test progress from sending headers before response assertions.
$passed = 0;
$check = static function (bool $condition, string $name) use (&$passed): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
    echo 'PASS: ' . $name . PHP_EOL;
    $passed++;
};
$check(e('<script>"&') === '&lt;script&gt;&quot;&amp;', 'XSS escaping');
$check(env('LUMERA_NONEXISTENT_TEST_VARIABLE', 'fallback') === 'fallback', 'Environment fallback');
$check(class_exists(App\Controllers\HomeController::class), 'PSR-4 controller autoload');
$router = new App\Core\Router();
$router->get('/', static function (): void { echo 'home'; });
ob_start(); $router->dispatch('GET', '/lumera-beauty/public/?q=1', '/lumera-beauty/public'); $result = ob_get_clean();
$check($result === 'home', 'Subdirectory routing and query string');
ob_start(); $router->dispatch('GET', '/missing'); $result = ob_get_clean();
$check(http_response_code() === 404 && str_contains($result, 'bulunamadı'), '404 response');
ob_start(); $router->dispatch('POST', '/'); ob_end_clean();
$check(http_response_code() === 405, 'Unsupported method returns 405');
ob_start(); (new App\Controllers\HomeController())->index(); $home = ob_get_clean();
$check(str_contains($home, 'lang="tr"') && str_contains($home, 'Kendinize ayırdığınız'), 'Home view renders');
try { App\Core\View::render('../bootstrap'); $blocked = false; } catch (InvalidArgumentException) { $blocked = true; }
$check($blocked, 'View traversal blocked');
$check(!str_contains(file_get_contents(BASE_PATH . '/.env.example'), 'DB_USERNAME=root'), 'No assumed database credentials');
require __DIR__ . '/security.php';
echo $passed . ' test passed.' . PHP_EOL;
ob_end_flush();
