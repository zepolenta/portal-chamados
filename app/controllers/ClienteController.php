<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cliente;
use App\Services\ValidadorDocumento;

final class ClienteController extends Controller
{
    public function index(): void
    {
        Auth::exigir('TECNICO', 'ADMIN');
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $this->view('clientes/index', ['titulo' => 'Clientes', 'clientes' => Cliente::listar($busca ?: null), 'busca' => $busca]);
    }

    public function form(): void
    {
        Auth::exigir('ADMIN');
        $id = $this->inteiro('id');
        $d = $id ? Cliente::porId($id) : null;
        if ($id && !$d) $this->redirecionar('clientes/index');
        $d ??= ['tipo_pessoa' => 'PJ', 'nome' => '', 'cpf_cnpj' => '', 'email' => '', 'telefone' => '', 'modalidade' => 'AVULSO',
                'ativo' => 1, 'plano' => '', 'horas_mes' => '', 'inicio_vigencia' => date('Y-m-d'), 'fim_vigencia' => ''];
        $erros = [];
        if ($this->ehPost()) {
            $this->validarCsrf();
            $d = [
                'tipo_pessoa' => ($_POST['tipo_pessoa'] ?? '') === 'PF' ? 'PF' : 'PJ',
                'nome' => $this->texto('nome'),
                'cpf_cnpj' => ValidadorDocumento::somenteDigitos($this->texto('cpf_cnpj')),
                'email' => mb_strtolower($this->texto('email')),
                'telefone' => $this->texto('telefone'),
                'modalidade' => ($_POST['modalidade'] ?? '') === 'MENSALISTA' ? 'MENSALISTA' : 'AVULSO',
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'plano' => $this->texto('plano'),
                'horas_mes' => $this->texto('horas_mes'),
                'inicio_vigencia' => $this->texto('inicio_vigencia'),
                'fim_vigencia' => $this->texto('fim_vigencia'),
            ];
            if (mb_strlen($d['nome']) < 3) $erros['nome'] = 'Informe o nome ou a razão social.';
            $rotulo = $d['tipo_pessoa'] === 'PF' ? 'CPF' : 'CNPJ';
            if (!ValidadorDocumento::valido($d['tipo_pessoa'], $d['cpf_cnpj'])) {
                $erros['cpf_cnpj'] = "$rotulo inválido. Confira os números digitados.";
            } elseif (Cliente::documentoEmUso($d['cpf_cnpj'], $id)) {
                $erros['cpf_cnpj'] = "Já existe um cliente cadastrado com este $rotulo.";
            }
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Informe um e-mail válido.';
            if ($d['modalidade'] === 'MENSALISTA') {
                if ($d['plano'] === '') $erros['plano'] = 'Informe o plano do contrato.';
                if (!ctype_digit((string) $d['horas_mes'])) $erros['horas_mes'] = 'Informe as horas por mês (número inteiro).';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['inicio_vigencia'])) $erros['inicio_vigencia'] = 'Informe o início da vigência.';
                if ($d['fim_vigencia'] && $d['fim_vigencia'] < $d['inicio_vigencia']) $erros['fim_vigencia'] = 'O fim deve ser posterior ao início.';
            }
            if (!$erros) {
                Cliente::salvar($id, $d);
                $this->flash('sucesso', 'Cliente salvo com sucesso.');
                $this->redirecionar('clientes/index');
            }
        }
        $this->view('clientes/form', ['titulo' => $id ? 'Editar cliente' : 'Novo cliente', 'd' => $d, 'id' => $id, 'erros' => $erros]);
    }
}
