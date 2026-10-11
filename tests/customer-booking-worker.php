<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
try{(new App\Services\CustomerAppointmentService())->confirm((int)$input['customer'],$input['draft']);echo 'CREATED';}catch(DomainException){echo 'BLOCKED';}
