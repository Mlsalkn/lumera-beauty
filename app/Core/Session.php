<?php
declare(strict_types=1);
namespace App\Core;

final class Session
{
    public static function start(string $name = 'lumera_session'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        if (headers_sent()) { throw new \RuntimeException('Oturum HTTP çıktısından önce başlatılmalıdır.'); }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $name)) {
            throw new \InvalidArgumentException('Geçersiz oturum adı.');
        }
        $path = BASE_PATH . '/storage/sessions';
        if (!is_dir($path) || !is_writable($path)) {
            throw new \RuntimeException('Özel oturum dizini yazılabilir olmalıdır.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_save_path($path);
        session_name($name);
        $secure = PHP_SAPI !== 'cli' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) { throw new \RuntimeException('Oturum başlatılamadı.'); }
    }

    public static function regenerate(): void
    {
        self::assertActive();
        if (!session_regenerate_id(true)) { throw new \RuntimeException('Oturum kimliği yenilenemedi.'); }
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        self::assertActive();
        $_SESSION = [];
        $params = session_get_cookie_params();
        if (PHP_SAPI !== 'cli') {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        if (!session_destroy()) { throw new \RuntimeException('Oturum sonlandırılamadı.'); }
    }

    public static function assertActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \LogicException('Önce Session::start çağrılmalıdır.');
        }
    }
}
