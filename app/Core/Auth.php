<?php
namespace App\Core;

use App\Models\Usuario;

/** Autenticação por sessão e controle de acesso por perfil. */
final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public static function user(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['usuario']) ? (int) $_SESSION['usuario']['id_usuario'] : null;
    }

    public static function perfil(): ?string
    {
        return $_SESSION['usuario']['perfil'] ?? null;
    }

    public static function ehEquipe(): bool
    {
        return in_array(self::perfil(), ['TECNICO', 'ADMIN'], true);
    }

    public static function tentarLogin(string $email, string $senha): bool
    {
        $u = Usuario::porEmail($email);
        if (!$u || !$u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        unset($u['senha_hash']);
        $_SESSION['usuario'] = $u;
        Usuario::registrarAcesso((int) $u['id_usuario']);
        return true;
    }

    public static function sair(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** Exige login e, opcionalmente, um dos perfis informados. */
    public static function exigir(string ...$perfis): void
    {
        if (!self::check()) {
            header('Location: ?r=auth/login');
            exit;
        }
        if ($perfis && !in_array(self::perfil(), $perfis, true)) {
            http_response_code(403);
            View::render('erros/404', ['titulo' => 'Acesso negado',
                'mensagem' => 'Seu perfil não tem permissão para acessar esta página.']);
            exit;
        }
    }
}
