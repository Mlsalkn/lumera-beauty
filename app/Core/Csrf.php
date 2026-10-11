<?php
declare(strict_types=1);
namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::assertActive();
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(mixed $submitted): bool
    {
        Session::assertActive();
        return is_string($submitted)
            && strlen($submitted) === 64
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $submitted);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . \e(self::token()) . '">';
    }
}
