<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--empty-only'){exit("CLI: php bin/install-schema.php --empty-only; mevcut tablolar varsa durur.\n");}
require dirname(__DIR__).'/bootstrap.php';
$config=require BASE_PATH.'/config/database.php';
if($config['database']!=='lumera_beauty_db'||env('MIGRATION_DB_USERNAME')===''){throw new RuntimeException('Hedef/migration hesabı eksik.');}
$pdo=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4',env('MIGRATION_DB_USERNAME'),env('MIGRATION_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$lock=$pdo->query("SELECT GET_LOCK('lumera_schema_install',10)")->fetchColumn();if((int)$lock!==1){throw new RuntimeException('Kurulum kilidi alınamadı.');}
try{
 $pdo->exec('CREATE DATABASE IF NOT EXISTS `lumera_beauty_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$pdo->exec('USE `lumera_beauty_db`');
 if($pdo->query('SHOW TABLES')->fetchColumn()!==false){fwrite(STDERR,"Mevcut tablolar bulundu. Hiçbir tablo değiştirilmedi.\n");exit(2);}
 foreach(explode(';',file_get_contents(BASE_PATH.'/database/export/lumera-schema.sql'))as$statement){if(trim($statement)!==''){$pdo->exec($statement);}}
 echo "Tam teslim şeması kuruldu. Kullanıcı veya şifre eklenmedi.\n";
}finally{$pdo->query("SELECT RELEASE_LOCK('lumera_schema_install')");}
