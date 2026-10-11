<?php
declare(strict_types=1);
namespace App\Services;

/** Configuration checks only: no network requests, mail delivery or DB writes. */
final class DeploymentReadiness
{
    public static function inspect(array $config): array
    {
        $issues = [];
        $url = parse_url((string)($config['APP_URL'] ?? ''));
        $host = strtolower($url['host'] ?? '');
        if (!$url || ($url['scheme'] ?? '') !== 'https' || $host === '' ||
            in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) ||
            str_ends_with($host, '.localhost') || isset($url['user']) || isset($url['pass'])) {
            $issues[] = 'APP_URL: erişilebilir bir HTTPS adresi gereklidir.';
        }
        if (($config['MAIL_DRIVER'] ?? 'disabled') !== 'smtp') {
            $issues[] = 'MAIL_DRIVER: gerçek SMTP yapılandırılmamış.';
        }
        foreach (['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD'] as $key) {
            if (trim((string)($config[$key] ?? '')) === '') {
                $issues[] = $key . ': yapılandırma eksik.';
            }
        }
        if (!in_array($config['SMTP_ENCRYPTION'] ?? '', ['tls', 'ssl'], true)) {
            $issues[] = 'SMTP_ENCRYPTION: tls veya ssl gereklidir.';
        }
        $port = filter_var($config['SMTP_PORT'] ?? '', FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) { $issues[] = 'SMTP_PORT: geçerli port gereklidir.'; }
        if (!filter_var($config['MAIL_FROM_ADDRESS'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'MAIL_FROM_ADDRESS: geçerli gönderen adresi gereklidir.';
        }
        $key = base64_decode((string)($config['MAIL_ENCRYPTION_KEY'] ?? ''), true);
        if ($key === false || strlen($key) !== 32) {
            $issues[] = 'MAIL_ENCRYPTION_KEY: 32 bayt Base64 anahtar gereklidir.';
        }
        return $issues;
    }
}
