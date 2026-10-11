<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
if(!in_array('--run',$argv,true)){fwrite(STDERR,"Kullanım: php bin/process-mail.php --run [--reminders]\n");exit(2);}
$queue=new App\Services\MailQueue();if(in_array('--reminders',$argv,true)){$queue->reminders();}
echo json_encode($queue->dispatch(),JSON_THROW_ON_ERROR).PHP_EOL;
