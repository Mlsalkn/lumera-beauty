<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Services\AuthService;
final class AuthMiddleware
{
    public static function user(): ?array
    {
        $user = AuthService::current();
        if (!$user) { header('Location: ' . \url('login'), true, 303); }
        return $user;
    }
}
