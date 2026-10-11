<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/bootstrap.php';
try {
    $data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    (new App\Services\AppointmentService())->create($data);
    echo "CREATED";
} catch (DomainException) { echo 'BLOCKED'; }
