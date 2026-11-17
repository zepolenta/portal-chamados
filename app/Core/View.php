<?php
namespace App\Core;

/** Renderização das Views dentro do layout padrão. */
final class View
{
    public static function render(string $template, array $dados = [], bool $comLayout = true): void
    {
        $base = dirname(__DIR__) . '/views/';
        extract($dados, EXTR_SKIP);
        ob_start();
        require $base . $template . '.php';
        $conteudo = ob_get_clean();
        if ($comLayout) {
            require $base . 'layout.php';
        } else {
            echo $conteudo;
        }
    }
}
