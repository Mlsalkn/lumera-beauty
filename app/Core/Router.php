<?php
declare(strict_types=1);
namespace App\Core;
final class Router
{
    private array $routes = [];
    public function get(string $path, callable $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][$path] = $handler; }
    public function dispatch(string $method, string $uri, string $base = ''): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = rtrim($base, '/');
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) { $path = substr($path, strlen($base)); }
        $path = '/' . trim($path, '/');
        if (isset($this->routes[$method][$path])) { ($this->routes[$method][$path])(); return; }
        $allowed = array_keys(array_filter($this->routes, static fn(array $routes): bool => isset($routes[$path])));
        $status = $allowed !== [] ? 405 : 404;
        http_response_code($status);
        if ($status === 405) { header('Allow: ' . implode(', ', $allowed)); }
        View::render('errors/error', ['title' => (string) $status, 'message' => $status === 404 ? 'Aradığınız sayfa bulunamadı.' : 'Bu istek yöntemi desteklenmiyor.']);
    }
}
