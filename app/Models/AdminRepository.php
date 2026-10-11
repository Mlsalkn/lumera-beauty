<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
final class AdminRepository
{
    public static function rows(string $sql, array $values = []): array
    {
        $query = Database::connection()->prepare($sql); $query->execute($values); return $query->fetchAll();
    }
    public static function one(string $sql, array $values = []): ?array { return self::rows($sql,$values)[0] ?? null; }
    public static function execute(string $sql, array $values = []): int
    {
        $query = Database::connection()->prepare($sql); $query->execute($values); return $query->rowCount();
    }
    public static function services(): array { return self::rows('SELECT * FROM services ORDER BY is_active DESC, category, name, id'); }
    public static function employees(): array { return self::rows('SELECT e.*, u.name, u.email, u.phone, u.is_active AS account_active FROM employees e JOIN users u ON u.id=e.user_id ORDER BY e.is_active DESC,u.name,e.id'); }
    public static function appointments(string $where = '1=1', array $values = [], ?int $limit = 500, bool $ascending = false): array
    {
        $suffix = $limit === null ? '' : ' LIMIT '.max(1,min(10000,$limit));
        $order = $ascending ? 'ASC' : 'DESC';
        return self::rows('SELECT a.*, c.name AS customer_name, u.name AS employee_name, s.name AS service_name FROM appointments a JOIN users c ON c.id=a.customer_id JOIN employees e ON e.id=a.employee_id JOIN users u ON u.id=e.user_id JOIN services s ON s.id=a.service_id WHERE '.$where.' ORDER BY a.start_at '.$order.',a.id '.$order.$suffix, $values);
    }
}
