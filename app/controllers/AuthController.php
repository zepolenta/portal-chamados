<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) $this->redirecionar('painel/index');
        $erro = null;
        $email = '';
        if ($this->ehPost()) {
            $this->validarCsrf();
            $email = $this->texto('email');
            $senha = (string) ($_POST['senha'] ?? '');
            // Limite simples contra força bruta: 5 tentativas a cada 5 minutos por sessão
            $tent = array_filter($_SESSION['tentativas'] ?? [], fn($t) => $t > time() - 300);
            if (count($tent) >= 5) {
                $erro = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
            } elseif ($email === '' || $senha === '') {
                $erro = 'Informe e-mail e senha.';
            } elseif (Auth::tentarLogin($email, $senha)) {
                unset($_SESSION['tentativas']);
                $this->redirecionar('painel/index');
            } else {
                $tent[] = time();
                $_SESSION['tentativas'] = $tent;
                $erro = 'E-mail ou senha inválidos.';
            }
        }
        \App\Core\View::render('auth/login', ['titulo' => 'Entrar', 'erro' => $erro, 'email' => $email], false);
    }

    public function sair(): void
    {
        Auth::sair();
        header('Location: ?r=auth/login');
        exit;
    }
}
