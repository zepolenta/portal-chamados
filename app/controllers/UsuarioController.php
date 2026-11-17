<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cliente;
use App\Models\Usuario;

final class UsuarioController extends Controller
{
    public function index(): void
    {
        Auth::exigir('ADMIN');
        $this->view('usuarios/index', ['titulo' => 'Usuários', 'usuarios' => Usuario::listar()]);
    }

    public function form(): void
    {
        Auth::exigir('ADMIN');
        $id = $this->inteiro('id');
        $d = $id ? Usuario::porId($id) : null;
        if ($id && !$d) $this->redirecionar('usuarios/index');
        $d ??= ['nome' => '', 'email' => '', 'perfil' => 'CLIENTE', 'id_cliente' => null, 'ativo' => 1];
        $erros = [];
        if ($this->ehPost()) {
            $this->validarCsrf();
            $d = [
                'nome' => $this->texto('nome'),
                'email' => mb_strtolower($this->texto('email')),
                'perfil' => array_key_exists($_POST['perfil'] ?? '', Usuario::PERFIS) ? $_POST['perfil'] : 'CLIENTE',
                'id_cliente' => $this->inteiro('id_cliente', $_POST),
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'senha' => (string) ($_POST['senha'] ?? ''),
            ];
            if (mb_strlen($d['nome']) < 3) $erros['nome'] = 'Informe o nome.';
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Informe um e-mail válido.';
            elseif (Usuario::emailEmUso($d['email'], $id)) $erros['email'] = 'Este e-mail já está em uso.';
            if ($d['perfil'] === 'CLIENTE' && (!$d['id_cliente'] || !Cliente::porId($d['id_cliente']))) $erros['id_cliente'] = 'Selecione a empresa do usuário.';
            if ((!$id || $d['senha'] !== '') && !self::senhaForte($d['senha'])) {
                $erros['senha'] = 'A senha deve ter 8+ caracteres, com letras e números.';
            }
            if ($id === Auth::id() && (!$d['ativo'] || $d['perfil'] !== 'ADMIN')) $erros['perfil'] = 'Você não pode remover o próprio acesso de administrador.';
            if (!$erros) {
                $id ? Usuario::atualizar($id, $d) : Usuario::criar($d);
                $this->flash('sucesso', 'Usuário salvo com sucesso.');
                $this->redirecionar('usuarios/index');
            }
        }
        $this->view('usuarios/form', ['titulo' => $id ? 'Editar usuário' : 'Novo usuário', 'd' => $d, 'id' => $id,
            'erros' => $erros, 'clientes' => Cliente::ativos()]);
    }

    private static function senhaForte(string $s): bool
    {
        return strlen($s) >= 8 && preg_match('/[A-Za-z]/', $s) && preg_match('/\d/', $s);
    }
}
