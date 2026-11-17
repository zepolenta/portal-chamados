<?php
namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Anexo;
use App\Models\Catalogo;
use App\Models\Chamado;
use App\Models\Cliente;
use App\Models\Interacao;

final class ChamadoController extends Controller
{
    public function index(): void
    {
        Auth::exigir();
        $f = [
            'status' => $_GET['status'] ?? '',
            'id_prioridade' => $this->inteiro('id_prioridade'),
            'id_cliente' => $this->inteiro('id_cliente'),
            'de' => $this->data($_GET['de'] ?? ''),
            'ate' => $this->data($_GET['ate'] ?? ''),
            'busca' => trim((string) ($_GET['busca'] ?? '')),
        ];
        if ($f['status'] !== 'ATIVOS' && !isset(Chamado::STATUS[$f['status']])) $f['status'] = '';
        $chamados = Chamado::listar(Auth::user(), $f);
        // Busca rápida: protocolo exato abre o chamado direto
        if ($f['busca'] !== '' && count($chamados) === 1 && strcasecmp($chamados[0]['protocolo'], $f['busca']) === 0) {
            $this->redirecionar('chamados/ver', ['id' => $chamados[0]['id_chamado']]);
        }
        $this->view('chamados/index', [
            'titulo' => Auth::ehEquipe() ? 'Fila de chamados' : 'Meus chamados',
            'chamados' => $chamados,
            'f' => $f,
            'prioridades' => Catalogo::prioridades(),
            'clientes' => Auth::ehEquipe() ? Cliente::ativos() : [],
        ]);
    }

    public function novo(): void
    {
        Auth::exigir();
        $u = Auth::user();
        $d = ['titulo' => '', 'descricao' => '', 'id_categoria' => '', 'id_prioridade' => 3, 'id_cliente' => $u['id_cliente']];
        $erros = [];
        if ($this->ehPost()) {
            $this->validarCsrf();
            $d = [
                'titulo' => $this->texto('titulo'),
                'descricao' => $this->texto('descricao'),
                'id_categoria' => $this->inteiro('id_categoria', $_POST),
                'id_prioridade' => $this->inteiro('id_prioridade', $_POST),
                'id_cliente' => Auth::ehEquipe() ? $this->inteiro('id_cliente', $_POST) : (int) $u['id_cliente'],
            ];
            if (mb_strlen($d['titulo']) < 5) $erros['titulo'] = 'Informe um título com pelo menos 5 caracteres.';
            if (mb_strlen($d['titulo']) > 120) $erros['titulo'] = 'O título pode ter no máximo 120 caracteres.';
            if (mb_strlen($d['descricao']) < 10) $erros['descricao'] = 'Descreva o problema com pelo menos 10 caracteres.';
            if (!$d['id_categoria'] || !Catalogo::categoriaExiste($d['id_categoria'])) $erros['id_categoria'] = 'Selecione a categoria.';
            if (!$d['id_prioridade'] || !Catalogo::prioridade($d['id_prioridade'])) $erros['id_prioridade'] = 'Selecione a prioridade.';
            if (!$d['id_cliente'] || !Cliente::porId($d['id_cliente'])) $erros['id_cliente'] = 'Selecione o cliente.';
            [$anexo, $erroAnexo] = Anexo::validarUpload($_FILES['anexo'] ?? null);
            if ($erroAnexo) $erros['anexo'] = $erroAnexo;
            if (!$erros) {
                $r = Chamado::abrir($d, $u, $anexo);
                $this->flash('sucesso', "Chamado {$r['protocolo']} aberto com sucesso. Acompanhe os prazos abaixo.");
                $this->redirecionar('chamados/ver', ['id' => $r['id']]);
            }
        }
        $this->view('chamados/novo', [
            'titulo' => 'Novo chamado', 'd' => $d, 'erros' => $erros,
            'categorias' => Catalogo::categorias(), 'prioridades' => Catalogo::prioridades(),
            'clientes' => Auth::ehEquipe() ? Cliente::ativos() : [],
        ]);
    }

    public function ver(): void
    {
        Auth::exigir();
        $c = $this->carregar($this->inteiro('id'));
        $this->view('chamados/ver', [
            'titulo' => 'Chamado ' . $c['protocolo'],
            'c' => $c,
            'interacoes' => Interacao::doChamado((int) $c['id_chamado'], Auth::ehEquipe()),
            'historico' => Chamado::historico((int) $c['id_chamado']),
            'transicoes' => array_keys(array_filter(Chamado::TRANSICOES[$c['status']],
                fn($perfis) => in_array(Auth::perfil(), $perfis, true))),
        ]);
    }

    public function responder(): void
    {
        Auth::exigir();
        if (!$this->ehPost()) $this->redirecionar('chamados/index');
        $this->validarCsrf();
        $c = $this->carregar($this->inteiro('id', $_POST));
        if (in_array($c['status'], ['FECHADO', 'CANCELADO'], true)) {
            $this->flash('erro', 'Chamado encerrado não recebe novas mensagens.');
            $this->redirecionar('chamados/ver', ['id' => $c['id_chamado']]);
        }
        $msg = $this->texto('mensagem');
        $interna = Auth::ehEquipe() && !empty($_POST['interna']);
        [$anexo, $erroAnexo] = Anexo::validarUpload($_FILES['anexo'] ?? null);
        if ($erroAnexo || ($msg === '' && !$anexo)) {
            $this->flash('erro', $erroAnexo ?: 'Escreva uma mensagem ou anexe um arquivo.');
            $this->redirecionar('chamados/ver', ['id' => $c['id_chamado']]);
        }
        $idInt = Interacao::criar((int) $c['id_chamado'], Auth::id(), $msg !== '' ? $msg : 'Arquivo anexado.', $interna);
        if ($anexo) Anexo::criar($idInt, $anexo);
        if (Auth::ehEquipe() && !$interna) Chamado::marcarPrimeiraResposta((int) $c['id_chamado']);
        // Resposta do cliente retoma o atendimento
        if (!Auth::ehEquipe() && $c['status'] === 'AGUARDANDO_CLIENTE') {
            Chamado::alterarStatus($c, 'EM_ATENDIMENTO', Auth::user());
        }
        $this->flash('sucesso', $interna ? 'Nota interna registrada (visível só para a equipe).' : 'Mensagem enviada.');
        $this->redirecionar('chamados/ver', ['id' => $c['id_chamado']]);
    }

    public function status(): void
    {
        Auth::exigir();
        if (!$this->ehPost()) $this->redirecionar('chamados/index');
        $this->validarCsrf();
        $c = $this->carregar($this->inteiro('id', $_POST));
        $novo = (string) ($_POST['status'] ?? '');
        if (!Chamado::podeTransitar(Auth::perfil(), $c['status'], $novo)) {
            $this->flash('erro', 'Mudança de status não permitida.');
        } else {
            Chamado::alterarStatus($c, $novo, Auth::user());
            $this->flash('sucesso', 'Status alterado para "' . Chamado::STATUS[$novo] . '".');
        }
        $this->redirecionar('chamados/ver', ['id' => $c['id_chamado']]);
    }

    public function assumir(): void
    {
        Auth::exigir('TECNICO', 'ADMIN');
        if (!$this->ehPost()) $this->redirecionar('chamados/index');
        $this->validarCsrf();
        $c = $this->carregar($this->inteiro('id', $_POST));
        if (in_array($c['status'], ['ABERTO', 'EM_ATENDIMENTO', 'AGUARDANDO_CLIENTE'], true)) {
            Chamado::assumir($c, Auth::user());
            $this->flash('sucesso', 'Você assumiu o chamado.');
        }
        $this->redirecionar('chamados/ver', ['id' => $c['id_chamado']]);
    }

    public function avaliar(): void
    {
        Auth::exigir('CLIENTE');
        if (!$this->ehPost()) $this->redirecionar('chamados/index');
        $this->validarCsrf();
        $c = $this->carregar($this->inteiro('id', $_POST));
        $nota = $this->inteiro('nota', $_POST);
        if ($c['status'] !== 'RESOLVIDO') {
            $this->flash('erro', 'Só é possível avaliar chamados resolvidos.');
        } elseif (!$nota || $nota < 1 || $nota > 5) {
            $this->flash('erro', 'Escolha uma nota de 1 a 5.');
        } else {
            Chamado::avaliar($c, $nota, mb_substr($this->texto('comentario'), 0, 500), Auth::user());
            $this->flash('sucesso', 'Obrigado pela avaliação! Chamado encerrado.');
        }
        $this->redirecionar('chamados/ver', ['id' => $c['id_chamado']]);
    }

    /** Download de anexo com checagem de permissão. */
    public function anexo(): void
    {
        Auth::exigir();
        $a = Anexo::comChamado((int) $this->inteiro('id'));
        $c = $a ? Chamado::porId((int) $a['id_chamado']) : null;
        if (!$a || !$c || !Chamado::visivelPara($c, Auth::user()) || ($a['interna'] && !Auth::ehEquipe())) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }
        $arq = App::config('upload_dir') . '/' . $a['caminho'];
        if (!is_file($arq)) { http_response_code(404); exit('Arquivo não encontrado.'); }
        header('Content-Type: ' . $a['tipo_mime']);
        header('Content-Length: ' . filesize($arq));
        header('Content-Disposition: attachment; filename="' . rawurlencode($a['nome_arquivo']) . '"');
        readfile($arq);
        exit;
    }

    private function carregar(?int $id): array
    {
        $c = $id ? Chamado::porId($id) : null;
        if (!$c || !Chamado::visivelPara($c, Auth::user())) {
            http_response_code(404);
            \App\Core\View::render('erros/404', ['titulo' => 'Chamado não encontrado',
                'mensagem' => 'O chamado não existe ou você não tem acesso a ele.']);
            exit;
        }
        return $c;
    }

    private function data(string $v): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
