<?php
declare(strict_types=1);
namespace App\Core;
final class View
{
    public static function render(string $view, array $data = []): void
    {
        if (!preg_match('~^[a-zA-Z0-9_/-]+$~', $view) || str_contains($view, '..')) { throw new \InvalidArgumentException('Geçersiz görünüm.'); }
        $file = BASE_PATH . '/resources/views/' . $view . '.php';
        if (!is_file($file)) { throw new \RuntimeException('Görünüm bulunamadı.'); }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = ob_get_clean();
        require BASE_PATH . '/resources/views/layouts/main.php';
    }
}
