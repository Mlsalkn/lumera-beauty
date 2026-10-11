<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class Database
{
    private static ?PDO $connection = null;
    public static function connect(bool $selectDatabase = true): PDO
    {
        $config = require BASE_PATH . '/config/database.php';
        if ($config['username'] === '') { throw new \RuntimeException('DB_USERNAME ortam değişkenini yapılandırın.'); }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['database']) || !ctype_digit($config['port'])) {
            throw new \RuntimeException('Geçersiz veritabanı yapılandırması.');
        }
        $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4';
        if ($selectDatabase) { $dsn .= ';dbname=' . $config['database']; }
        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    }
    public static function connection(): PDO { return self::$connection ??= self::connect(); }
}
