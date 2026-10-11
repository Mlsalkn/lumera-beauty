<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__);
require BASE_PATH . '/vendor/autoload.php';
App\Core\Environment::load(BASE_PATH . '/.env');
date_default_timezone_set((require BASE_PATH . '/config/app.php')['timezone']);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args','1');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php.log');
set_exception_handler(static function (Throwable $error): void {
    // Never log exception messages/arguments: they may contain credentials or SQL values.
    $reference=bin2hex(random_bytes(6));
    error_log('error_ref='.$reference.' type='.get_class($error).' file='.basename($error->getFile()).' line='.$error->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "İşlem tamamlanamadı. storage/logs/php.log kaydını kontrol edin.\n");
        exit(1);
    }
    http_response_code(500);
    App\Core\View::render('errors/error', ['title' => 'İşlem tamamlanamadı', 'message' => 'Lütfen daha sonra tekrar deneyin.']);
});
