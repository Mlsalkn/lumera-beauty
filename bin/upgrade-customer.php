<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/bootstrap.php';
if(($argv[1]??'')!=='--apply'){exit("Kullanım: php bin/upgrade-customer.php --apply; ayrı MIGRATION_DB_USERNAME/MIGRATION_DB_PASSWORD hesabı gerekir.\n");}
$config=require BASE_PATH.'/config/database.php';
if($config['database']!=='lumera_beauty_db'||env('MIGRATION_DB_USERNAME')===''){throw new RuntimeException('Hedef ve migration hesabı geçersiz.');}
$pdo=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',env('MIGRATION_DB_USERNAME'),env('MIGRATION_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute(['lumera_customer_migration']);if((int)$lock->fetchColumn()!==1){throw new RuntimeException('Migration kilidi alınamadı.');}
try{
 foreach(explode(';',file_get_contents(BASE_PATH.'/database/migrations/003_customer_modules.sql'))as$sql){
  if(trim($sql)===''){continue;}
  if(preg_match('/ALTER TABLE (\w+) ADD COLUMN (\w+)/',$sql,$parts)){$query=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$query->execute([$parts[1],$parts[2]]);if($query->fetchColumn()){continue;}}
  $pdo->exec($sql);
 }
 echo "Customer migration tamamlandı; mevcut kayıtlar korundu.\n";
}finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute(['lumera_customer_migration']);}
