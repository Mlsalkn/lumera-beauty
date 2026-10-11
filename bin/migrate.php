<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--fresh-only') {
    fwrite(STDERR, "Usage: php bin/migrate.php --fresh-only\nYalnızca hiç tablosu olmayan lumera_beauty_db için kullanın.\n"); exit(1);
}
$config = require BASE_PATH . '/config/database.php';
if ($config['database'] !== 'lumera_beauty_db') { throw new RuntimeException('Migration yalnızca lumera_beauty_db için kullanılabilir.'); }
$server = App\Core\Database::connect(false);
$server->exec(file_get_contents(BASE_PATH . '/database/migrations/001_create_database.sql'));
$pdo = App\Core\Database::connection();
if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) {
    fwrite(STDERR, "Mevcut tablolar bulundu. Hiçbir tablo değiştirilmedi; şemayı önce inceleyin.\n"); exit(1);
}
// MySQL DDL autocommits. Failures can leave partial NEW tables; never drop them automatically.
$sql = file_get_contents(BASE_PATH . '/database/schema.sql');
foreach (explode(';', $sql) as $statement) {
    if (trim($statement) !== '') { $pdo->exec($statement); }
}
echo "Yeni boş veritabanı şeması oluşturuldu. Kullanıcı veya şifre eklenmedi.\n";
