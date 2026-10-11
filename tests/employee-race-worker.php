<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
try{
 if($input['job']==='leave'){(new App\Services\EmployeeOperationsService())->reviewLeave($input['id'],'approved','QA concurrency',$input['admin']);}
 else{(new App\Services\AppointmentService())->create($input['booking'],$input['admin']);}
 echo 'OK';
}catch(DomainException){echo 'BLOCKED';}
