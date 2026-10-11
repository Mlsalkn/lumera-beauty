<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/bootstrap.php';
if (($argv[1] ?? '') !== '--apply') { exit("Kullanım: php bin/upgrade-admin.php --apply\nAyrı migration hesabını MIGRATION_DB_USERNAME / MIGRATION_DB_PASSWORD ortamında sağlayın.\n"); }
$config = require BASE_PATH . '/config/database.php';
if ($config['database'] !== 'lumera_beauty_db' || env('MIGRATION_DB_USERNAME') === '') { throw new RuntimeException('Migration hesabı ve hedef veritabanı gerekli.'); }
$pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4', env('MIGRATION_DB_USERNAME'), env('MIGRATION_DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$locked = $pdo->prepare('SELECT GET_LOCK(?, 10)'); $locked->execute(['lumera_admin_migration']);
if ((int)$locked->fetchColumn() !== 1) { throw new RuntimeException('Migration kilidi alınamadı.'); }
try {
    $column = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'image_path'"); $column->execute();
    $hasImage = (bool)$column->fetchColumn();
    foreach (explode(';', file_get_contents(BASE_PATH . '/database/migrations/002_admin_modules.sql')) as $sql) {
        if (trim($sql) === '') { continue; }
        if ($hasImage && str_contains($sql, 'ALTER TABLE services')) { continue; }
        $pdo->exec($sql);
    }
    echo "Admin migration tamamlandı. Mevcut kayıtlar korunmuştur.\n";
} finally { $release = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute(['lumera_admin_migration']); }
