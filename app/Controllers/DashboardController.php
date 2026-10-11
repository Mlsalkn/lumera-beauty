<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\View;
use App\Middleware\{AuthMiddleware, RoleMiddleware};
final class DashboardController
{
    public function index(string $role): void
    {
        $user = AuthMiddleware::user();
        if (!$user || !RoleMiddleware::authorize($user, $role)) { return; }
        $labels = ['CUSTOMER' => 'Müşteri', 'EMPLOYEE' => 'Çalışan', 'ADMIN' => 'Yönetici'];
        View::render(strtolower($role) . '/index', ['title' => $labels[$role] . ' paneli · Luméra', 'user' => $user, 'roleLabel' => $labels[$role]]);
    }
}
