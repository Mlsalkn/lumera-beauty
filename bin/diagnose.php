<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl'] as $extension) {
    echo $extension . ': ' . (extension_loaded($extension) ? 'OK' : 'EKSİK') . PHP_EOL;
}
try {
    $pdo = App\Core\Database::connection();
    echo 'DB bağlantısı: OK' . PHP_EOL;
    echo 'DB sürümü: ' . $pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'Tablolar: ' . implode(', ', $tables) . PHP_EOL;
    foreach (['users', 'employees', 'services', 'employee_services', 'working_hours', 'appointments', 'reviews'] as $table) {
        if (!in_array($table, $tables, true)) { echo $table . ': EKSİK' . PHP_EOL; continue; }
        echo $table . ': ' . implode(', ', array_column($pdo->query('DESCRIBE `' . $table . '`')->fetchAll(), 'Field')) . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "DB bağlantısı doğrulanamadı. DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD ve çalışan sunucuyu kontrol edin.\n");
    exit(1);
}
