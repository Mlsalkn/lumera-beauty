<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class User
{
    public function findByEmail(string $email): ?array
    {
        $query = Database::connection()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        return $query->fetch() ?: null;
    }
    public function find(int $id): ?array
    {
        $query = Database::connection()->prepare('SELECT * FROM users WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }
    public function create(string $name, string $email, ?string $phone, string $hash, string $role = 'CUSTOMER'): int
    {
        if (!in_array($role, ['CUSTOMER', 'EMPLOYEE', 'ADMIN'], true)) { throw new \InvalidArgumentException('Geçersiz rol.'); }
        $query = Database::connection()->prepare('INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)');
        $query->execute([$name, $email, $phone, $hash, $role]);
        return (int) Database::connection()->lastInsertId();
    }
    public function rehash(int $id, string $oldHash, string $newHash): void
    {
        $query = Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
        $query->execute([$newHash, $id, $oldHash]);
    }
}
