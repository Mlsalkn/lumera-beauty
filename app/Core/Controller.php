<?php
declare(strict_types=1);
namespace App\Core;
abstract class Controller
{
    protected function render(string $view, array $data = []): void { View::render($view, $data); }
}
