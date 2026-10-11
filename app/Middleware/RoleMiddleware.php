<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\View;
final class RoleMiddleware
{
    public static function authorize(array $user, string $role): bool
    {
        if ($user['role'] === $role) { return true; }
        http_response_code(403);
        View::render('errors/error', ['title' => '403', 'message' => 'Bu sayfaya erişim yetkiniz bulunmuyor.']);
        return false;
    }
}
