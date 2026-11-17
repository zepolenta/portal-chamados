<?php
/**
 * Carga de dados de DEMONSTRAÇÃO (empresas e pessoas fictícias).
 * Uso: php database/seed.php   (rode depois de importar portal_chamados_polenta.sql)
 * Senha de todos os usuários de demonstração: Polenta2026
 */
declare(strict_types=1);

require __DIR__ . '/../app/Core/App.php';
require __DIR__ . '/../app/helpers.php';

use App\Core\App;
use App\Core\Database;
use App\Services\ServicoSla;

App::iniciar();
$db = Database::pdo();
$sla = ServicoSla::padrao();

if ((int) $db->query('SELECT COUNT(*) FROM usuario')->fetchColumn() > 0) {
    fwrite(STDERR, "O banco já tem usuários; a carga de demonstração não foi aplicada.\n");
    exit(1);
}

$senha = password_hash('Polenta2026', PASSWORD_DEFAULT);
$ins = fn(string $sql, array $p) => ($db->prepare($sql)->execute($p) ? (int) $db->lastInsertId() : 0);

// Clientes fictícios (CPF/CNPJ com dígitos verificadores válidos, gerados para teste)
$cli = [];
$cli['contab'] = $ins('INSERT INTO cliente (tipo_pessoa,nome,cpf_cnpj,email,telefone,modalidade) VALUES (?,?,?,?,?,?)',
    ['PJ', 'Contabilidade Exemplo Ltda', '11222333000181', 'contato@contabexemplo.com.br', '(11) 4000-1000', 'MENSALISTA']);
$cli['studio'] = $ins('INSERT INTO cliente (tipo_pessoa,nome,cpf_cnpj,email,telefone,modalidade) VALUES (?,?,?,?,?,?)',
    ['PJ', 'Studio Fictício Arquitetura ME', '45723174000110', 'ti@studioficticio.com.br', '(11) 4000-2000', 'AVULSO']);
$cli['pf'] = $ins('INSERT INTO cliente (tipo_pessoa,nome,cpf_cnpj,email,telefone,modalidade) VALUES (?,?,?,?,?,?)',
    ['PF', 'Maria Exemplo da Silva', '52998224725', 'maria.exemplo@email.com', '(11) 90000-3000', 'AVULSO']);
$ins('INSERT INTO contrato (id_cliente,plano,horas_mes,inicio_vigencia) VALUES (?,?,?,?)', [$cli['contab'], 'Essencial', 8, date('Y-01-01')]);

// Usuários
$u = [];
$u['admin'] = $ins('INSERT INTO usuario (id_cliente,nome,email,senha_hash,perfil) VALUES (?,?,?,?,?)', [null, 'Administrador Polenta', 'admin@polenta.tv', $senha, 'ADMIN']);
$u['tec'] = $ins('INSERT INTO usuario (id_cliente,nome,email,senha_hash,perfil) VALUES (?,?,?,?,?)', [null, 'Técnico Polenta', 'tecnico@polenta.tv', $senha, 'TECNICO']);
$u['ana'] = $ins('INSERT INTO usuario (id_cliente,nome,email,senha_hash,perfil) VALUES (?,?,?,?,?)', [$cli['contab'], 'Ana Contábil', 'cliente@contabexemplo.com.br', $senha, 'CLIENTE']);
$u['beto'] = $ins('INSERT INTO usuario (id_cliente,nome,email,senha_hash,perfil) VALUES (?,?,?,?,?)', [$cli['studio'], 'Beto Studio', 'cliente@studioficticio.com.br', $senha, 'CLIENTE']);
$u['maria'] = $ins('INSERT INTO usuario (id_cliente,nome,email,senha_hash,perfil) VALUES (?,?,?,?,?)', [$cli['pf'], 'Maria Exemplo', 'maria.exemplo@email.com', $senha, 'CLIENTE']);

// Chamados de exemplo em vários estados
$prior = [];
foreach ($db->query('SELECT * FROM prioridade_sla') as $p) $prior[(int) $p['id_prioridade']] = $p;
$seq = 0;
$novo = function (int $cliente, int $solic, ?int $tec, int $cat, int $pri, string $titulo, string $desc, string $abertoEm, string $status)
    use ($ins, $sla, $prior, &$seq, $u, $db): int {
    $ab = new DateTimeImmutable($abertoEm);
    $pz = $sla->calcularPrazos($ab, (int) $prior[$pri]['min_resposta'], (int) $prior[$pri]['min_solucao']);
    $seq++;
    $id = $ins('INSERT INTO chamado (protocolo,id_cliente,id_solicitante,id_tecnico,id_categoria,id_prioridade,titulo,descricao,status,aberto_em,prazo_resposta,prazo_solucao,primeira_resposta_em,resolvido_em,fechado_em,nota_avaliacao)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        'PL' . $ab->format('Y') . str_pad((string) $seq, 6, '0', STR_PAD_LEFT), $cliente, $solic, $tec, $cat, $pri, $titulo, $desc, $status,
        $ab->format('Y-m-d H:i:s'), $pz['prazo_resposta']->format('Y-m-d H:i:s'), $pz['prazo_solucao']->format('Y-m-d H:i:s'),
        $status !== 'ABERTO' ? $ab->modify('+40 minutes')->format('Y-m-d H:i:s') : null,
        in_array($status, ['RESOLVIDO', 'FECHADO'], true) ? $ab->modify('+3 hours')->format('Y-m-d H:i:s') : null,
        $status === 'FECHADO' ? $ab->modify('+1 day')->format('Y-m-d H:i:s') : null,
        $status === 'FECHADO' ? 5 : null,
    ]);
    $ins('INSERT INTO historico_status (id_chamado,id_usuario,status_anterior,status_novo,alterado_em) VALUES (?,?,?,?,?)', [$id, $solic, null, 'ABERTO', $ab->format('Y-m-d H:i:s')]);
    $trilha = ['EM_ATENDIMENTO' => ['ABERTO'], 'AGUARDANDO_CLIENTE' => ['ABERTO', 'EM_ATENDIMENTO'],
               'RESOLVIDO' => ['ABERTO', 'EM_ATENDIMENTO'], 'FECHADO' => ['ABERTO', 'EM_ATENDIMENTO', 'RESOLVIDO']][$status] ?? [];
    $t = $ab;
    foreach ($trilha as $i => $de) {
        $para = $trilha[$i + 1] ?? $status;
        $t = $t->modify('+40 minutes');
        $ins('INSERT INTO historico_status (id_chamado,id_usuario,status_anterior,status_novo,alterado_em) VALUES (?,?,?,?,?)', [$id, $tec ?? $u['tec'], $de, $para, $t->format('Y-m-d H:i:s')]);
    }
    return $id;
};

$hoje = new DateTimeImmutable();
$c1 = $novo($cli['contab'], $u['ana'], $u['tec'], 2, 2, 'Internet caindo várias vezes ao dia', "A conexão do escritório cai a cada 1 ou 2 horas desde segunda-feira.\nAfeta os 6 computadores.", $hoje->modify('-3 days')->format('Y-m-d 09:15:00'), 'EM_ATENDIMENTO');
$ins('INSERT INTO interacao (id_chamado,id_usuario,mensagem,interna,criado_em) VALUES (?,?,?,?,?)', [$c1, $u['tec'], 'Olá, Ana! Vou verificar o roteador remotamente. Pode me dizer o modelo do equipamento?', 0, $hoje->modify('-3 days')->format('Y-m-d 09:55:00')]);
$ins('INSERT INTO interacao (id_chamado,id_usuario,mensagem,interna,criado_em) VALUES (?,?,?,?,?)', [$c1, $u['tec'], 'Firmware do roteador desatualizado. Agendar atualização fora do expediente.', 1, $hoje->modify('-3 days')->format('Y-m-d 10:20:00')]);
$novo($cli['contab'], $u['ana'], null, 4, 3, 'Impressora do financeiro não imprime em cores', 'A impressora imprime apenas em preto e branco desde ontem.', $hoje->modify('-1 day')->format('Y-m-d 14:00:00'), 'ABERTO');
$novo($cli['studio'], $u['beto'], $u['tec'], 3, 1, 'E-mail da empresa fora do ar', 'Ninguém consegue enviar nem receber e-mails.', $hoje->modify('-6 days')->format('Y-m-d 10:00:00'), 'AGUARDANDO_CLIENTE');
$novo($cli['studio'], $u['beto'], $u['tec'], 5, 4, 'Instalar software de renderização', 'Precisamos instalar o software no notebook novo.', $hoje->modify('-10 days')->format('Y-m-d 11:00:00'), 'FECHADO');
$novo($cli['pf'], $u['maria'], $u['tec'], 1, 3, 'Notebook muito lento', 'O notebook demora mais de 5 minutos para ligar.', $hoje->modify('-2 days')->format('Y-m-d 16:30:00'), 'RESOLVIDO');

echo "Carga de demonstração aplicada. Usuários (senha Polenta2026):\n";
echo "  admin@polenta.tv (Administrador)\n  tecnico@polenta.tv (Técnico)\n  cliente@contabexemplo.com.br (Cliente mensalista PJ)\n";
echo "  cliente@studioficticio.com.br (Cliente avulso PJ)\n  maria.exemplo@email.com (Cliente avulso PF)\n";
