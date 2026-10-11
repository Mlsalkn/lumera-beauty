<?php
declare(strict_types=1);
namespace App\Core;
final class Input
{
    public static function text(array $data, string $key, int $max = 255, bool $required = true): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8')) { throw new \DomainException('Geçersiz alan: '.$key); }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) { throw new \DomainException('Alan uzunluğu veya içeriği geçersiz: '.$key); }
        return $value;
    }
    public static function id(mixed $value): int
    {
        if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value < 1 || strlen((string)$value) > 10) { throw new \DomainException('Geçerli bir kayıt seçin.'); }
        return (int)$value;
    }
    public static function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) { throw new \DomainException('Geçerli bir tarih girin.'); }
        return $date;
    }
    public static function datetime(string $value): \DateTimeImmutable
    {
        $value = str_replace('T', ' ', $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
        if (!$date || $date->format('Y-m-d H:i') !== $value) { throw new \DomainException('Geçerli bir tarih ve saat girin.'); }
        return $date;
    }
}
