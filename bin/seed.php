<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--sample-services') {
    fwrite(STDERR, "Usage: php bin/seed.php --sample-services\n"); exit(1);
}
$config = require BASE_PATH . '/config/database.php';
if ($config['database'] !== 'lumera_beauty_db') { throw new RuntimeException('Seed yalnızca lumera_beauty_db için kullanılabilir.'); }
$services = require BASE_PATH . '/database/seeds/services.php';
$pdo = App\Core\Database::connection();
$lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
$lock->execute(['lumera_beauty_db_sample_services']);
if ((int) $lock->fetchColumn() !== 1) { throw new RuntimeException('Seed kilidi alınamadı.'); }
$inserted = 0;
try {
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO services (name, description, category, duration_minutes, price, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($services as $service) {
        $find->execute([$service['name']]);
        if ($find->fetchColumn() !== false) { continue; }
        $insert->execute([$service['name'], $service['description'], $service['category'], $service['duration_minutes'], $service['price']]);
        $inserted++;
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    throw $error;
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute(['lumera_beauty_db_sample_services']);
}
echo $inserted . " örnek hizmet eklendi. Mevcut hizmetler değiştirilmedi.\n";
