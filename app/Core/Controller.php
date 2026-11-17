<?php
namespace App\Core;

/** Classe base dos Controllers: render, redirecionamento, flash e CSRF. */
abstract class Controller
{
    protected function view(string $template, array $dados = []): void
    {
        View::render($template, $dados);
    }

    protected function redirecionar(string $rota, array $params = []): never
    {
        $qs = 'r=' . $rota . ($params ? '&' . http_build_query($params) : '');
        header('Location: ?' . $qs);
        exit;
    }

    protected function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
    }

    protected function ehPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Valida o token CSRF de todo POST. */
    protected function validarCsrf(): void
    {
        // Envio maior que o post_max_size: o PHP descarta $_POST e $_FILES
        $tamanho = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if (!$_POST && !$_FILES && $tamanho > ini_bytes((string) ini_get('post_max_size'))) {
            $this->flash('erro', 'O envio excede o limite de ' . tamanho_br(limite_upload_bytes()) . '. Escolha um arquivo menor.');
            $volta = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);
            header('Location: ?' . ($volta ?: 'r=painel/index'));
            exit;
        }
        $token = $_POST['_csrf'] ?? '';
        if (!is_string($token) || !hash_equals(self::tokenCsrf(), $token)) {
            http_response_code(419);
            View::render('erros/404', ['titulo' => 'Sessão expirada',
                'mensagem' => 'O formulário expirou. Volte, atualize a página e tente novamente.']);
            exit;
        }
    }

    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }

    protected function inteiro(string $chave, array $origem = null): ?int
    {
        $origem ??= $_GET;
        $v = filter_var($origem[$chave] ?? null, FILTER_VALIDATE_INT);
        return $v === false ? null : $v;
    }

    protected function texto(string $chave): string
    {
        return trim((string) ($_POST[$chave] ?? ''));
    }
}
