<?php
declare(strict_types=1);
function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $path = ''): string
{
    $config = require BASE_PATH . '/config/app.php';
    return $config['url'] . '/' . ltrim($path, '/');
}

function booking_url(?int $serviceId = null): string
{
    $user = App\Services\AuthService::current();
    $role = $user['role'] ?? 'CUSTOMER';
    if ($role === 'ADMIN') { return url('admin/appointments'); }
    if ($role === 'EMPLOYEE') { return url('employee/calendar'); }
    return url($serviceId ? 'customer/book?step=2&service_id='.$serviceId : 'customer/book');
}
