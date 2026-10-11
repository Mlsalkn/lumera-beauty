<?php
declare(strict_types=1);
namespace App\Core;

final class Password
{
    public static function isAcceptable(string $password): bool
    {
        return mb_check_encoding($password, 'UTF-8')
            && mb_strlen($password) >= 12
            && strlen($password) <= 72
            && trim($password) !== ''
            && !str_contains($password, "\0");
    }

    public static function hash(string $password): string
    {
        if (!self::isAcceptable($password)) { throw new \InvalidArgumentException('Parola politikası karşılanmıyor.'); }
        // Explicit bcrypt avoids an accidental change to the 72-byte policy on a PHP upgrade.
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verify(string $password, string $hash): bool
    {
        if (strlen($password) > 72 || str_contains($password, "\0")) { return false; }
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
