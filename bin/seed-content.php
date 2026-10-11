<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--sample-content')exit("CLI: php bin/seed-content.php --sample-content\n");
require dirname(__DIR__).'/bootstrap.php';
echo json_encode((new App\Services\SampleContentService())->seed(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
