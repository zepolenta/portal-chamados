<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Chamado;

final class PainelController extends Controller
{
    public function index(): void
    {
        Auth::exigir();
        $dados = Chamado::indicadores(Auth::user());
        $ativos = array_values(array_filter($dados['lista'], fn($c) => $c['situacao_sla'] !== 'ENCERRADO'));
        $this->view('painel/index', [
            'titulo' => 'Painel',
            'ind' => $dados['indicadores'],
            'ativos' => array_slice($ativos, 0, 10),
            'recentes' => array_slice(array_values(array_filter($dados['lista'], fn($c) => $c['situacao_sla'] === 'ENCERRADO')), 0, 5),
        ]);
    }
}
