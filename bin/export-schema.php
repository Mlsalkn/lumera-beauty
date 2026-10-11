<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--schema-only'){exit("CLI: php bin/export-schema.php --schema-only\n");}
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);sort($tables);
$column=$pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
$foreign=$pdo->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll();
$rules=$pdo->query('SELECT TABLE_NAME,CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()')->fetchAll();
$metadata=['tables'=>[],'foreign_keys'=>$foreign,'rules'=>$rules];$ddl=[];
foreach($tables as$table){if(!preg_match('/^[a-z_]+$/',$table)){throw new RuntimeException('Beklenmeyen tablo adı.');}$column->execute([$table]);$metadata['tables'][$table]=$column->fetchAll();$row=$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM);$ddl[$table]=preg_replace('/\sAUTO_INCREMENT=\d+/','',$row[1]);}
// Topological order keeps parent tables before their actual foreign-key children.
$ordered=[];$remaining=$tables;while($remaining){$progress=false;foreach($remaining as$i=>$table){$dependencies=array_column(array_filter($foreign,static fn($f)=>$f['TABLE_NAME']===$table&&$f['REFERENCED_TABLE_NAME']!==$table),'REFERENCED_TABLE_NAME');if(!array_diff($dependencies,$ordered)){$ordered[]=$table;unset($remaining[$i]);$progress=true;}}if(!$progress){throw new RuntimeException('Döngülü ilişki: kontrollü dışa aktarım durdu.');}}
$sql="-- Luméra Beauty: structure only - no rows, credentials, counters, DROP or database switch.\n-- Import only into a NEW EMPTY selected database. MariaDB 10.4 format.\nSET NAMES utf8mb4;\n\n";foreach($ordered as$table){$sql.=$ddl[$table].";\n\n";}
$directory=BASE_PATH.'/database/export';if(!is_dir($directory)){mkdir($directory,0755,true);}file_put_contents($directory.'/lumera-schema.sql',$sql);
$docs=BASE_PATH.'/docs';if(!is_dir($docs)){mkdir($docs,0755,true);}file_put_contents($docs.'/schema-metadata.json',json_encode($metadata,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$mermaid="erDiagram\n";foreach($metadata['tables']as$table=>$columns){$mermaid.="    $table {\n";foreach($columns as$c){$type=preg_replace('/[^a-zA-Z0-9_]/','_', $c['COLUMN_TYPE']);$key=$c['COLUMN_KEY']==='PRI'?' PK':($c['COLUMN_KEY']==='UNI'?' UK':'');if(array_filter($foreign,static fn($f)=>$f['TABLE_NAME']===$table&&$f['COLUMN_NAME']===$c['COLUMN_NAME'])){$key=$key?' '.$c['COLUMN_KEY'].',FK':' FK';if($c['COLUMN_KEY']==='PRI'){$key=' PK,FK';}if($c['COLUMN_KEY']==='UNI'){$key=' UK,FK';}}$mermaid.="        $type {$c['COLUMN_NAME']}$key\n";}$mermaid.="    }\n";}
foreach($foreign as$f){$c=array_values(array_filter($metadata['tables'][$f['TABLE_NAME']],static fn($c)=>$c['COLUMN_NAME']===$f['COLUMN_NAME']))[0];$parent=$c['IS_NULLABLE']==='YES'?'|o':'||';$child=in_array($c['COLUMN_KEY'],['PRI','UNI'],true)&&count(array_filter($metadata['tables'][$f['TABLE_NAME']],static fn($c)=>$c['COLUMN_KEY']==='PRI'))<=1?'o|':'o{';$mermaid.="    {$f['REFERENCED_TABLE_NAME']} $parent--$child {$f['TABLE_NAME']} : {$f['COLUMN_NAME']}\n";}
file_put_contents($docs.'/database-er.mmd',$mermaid);echo count($tables).' tables / '.count($foreign)." foreign keys exported; zero data rows selected.\n";
