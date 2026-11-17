<?php
namespace App\Models;

use App\Services\ServicoProtocolo;
use App\Services\ServicoSla;
use DateTimeImmutable;

final class Chamado extends Model
{
    public const STATUS = [
        'ABERTO' => 'Aberto', 'EM_ATENDIMENTO' => 'Em atendimento', 'AGUARDANDO_CLIENTE' => 'Aguardando cliente',
        'RESOLVIDO' => 'Resolvido', 'FECHADO' => 'Fechado', 'CANCELADO' => 'Cancelado',
    ];

    /** Transições permitidas (RN05): status atual => [novo status => perfis autorizados] */
    public const TRANSICOES = [
        'ABERTO'             => ['EM_ATENDIMENTO' => ['TECNICO', 'ADMIN'], 'CANCELADO' => ['CLIENTE', 'TECNICO', 'ADMIN']],
        'EM_ATENDIMENTO'     => ['AGUARDANDO_CLIENTE' => ['TECNICO', 'ADMIN'], 'RESOLVIDO' => ['TECNICO', 'ADMIN']],
        'AGUARDANDO_CLIENTE' => ['EM_ATENDIMENTO' => ['TECNICO', 'ADMIN'], 'RESOLVIDO' => ['TECNICO', 'ADMIN']],
        'RESOLVIDO'          => ['FECHADO' => ['TECNICO', 'ADMIN'], 'EM_ATENDIMENTO' => ['CLIENTE', 'TECNICO', 'ADMIN']],
        'FECHADO'            => [],
        'CANCELADO'          => [],
    ];

    private const SELECT_BASE = 'SELECT ch.*, cl.nome AS cliente, cl.modalidade, cl.tipo_pessoa, cat.nome AS categoria,
            p.nome AS prioridade, p.min_resposta, p.min_solucao, s.nome AS solicitante, t.nome AS tecnico
        FROM chamado ch
        JOIN cliente cl ON cl.id_cliente = ch.id_cliente
        JOIN categoria cat ON cat.id_categoria = ch.id_categoria
        JOIN prioridade_sla p ON p.id_prioridade = ch.id_prioridade
        JOIN usuario s ON s.id_usuario = ch.id_solicitante
        LEFT JOIN usuario t ON t.id_usuario = ch.id_tecnico';

    public static function podeTransitar(string $perfil, string $de, string $para): bool
    {
        return in_array($perfil, self::TRANSICOES[$de][$para] ?? [], true);
    }

    /** Lista chamados respeitando o escopo do usuário (cliente vê só a própria empresa — RNF03). */
    public static function listar(array $usuario, array $f = []): array
    {
        $where = [];
        $p = [];
        if ($usuario['perfil'] === 'CLIENTE') {
            $where[] = 'ch.id_cliente = ?';
            $p[] = $usuario['id_cliente'];
        } elseif (!empty($f['id_cliente'])) {
            $where[] = 'ch.id_cliente = ?';
            $p[] = $f['id_cliente'];
        }
        if (!empty($f['status'])) {
            if ($f['status'] === 'ATIVOS') {
                $where[] = "ch.status IN ('ABERTO','EM_ATENDIMENTO','AGUARDANDO_CLIENTE')";
            } else {
                $where[] = 'ch.status = ?';
                $p[] = $f['status'];
            }
        }
        if (!empty($f['id_prioridade'])) { $where[] = 'ch.id_prioridade = ?'; $p[] = $f['id_prioridade']; }
        if (!empty($f['de']))  { $where[] = 'ch.aberto_em >= ?'; $p[] = $f['de'] . ' 00:00:00'; }
        if (!empty($f['ate'])) { $where[] = 'ch.aberto_em <= ?'; $p[] = $f['ate'] . ' 23:59:59'; }
        if (!empty($f['busca'])) {
            $where[] = '(ch.protocolo LIKE ? OR ch.titulo LIKE ?)';
            $p[] = '%' . $f['busca'] . '%';
            $p[] = '%' . $f['busca'] . '%';
        }
        $sql = self::SELECT_BASE . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        // Fila: ativos primeiro, depois prioridade e vencimento
        $sql .= " ORDER BY (ch.status IN ('ABERTO','EM_ATENDIMENTO','AGUARDANDO_CLIENTE')) DESC,
                  ch.id_prioridade, ch.prazo_solucao, ch.id_chamado DESC LIMIT 300";
        return self::comSituacao(self::todos($sql, $p));
    }

    public static function porId(int $id): ?array
    {
        $c = self::um(self::SELECT_BASE . ' WHERE ch.id_chamado = ?', [$id]);
        return $c ? self::comSituacao([$c])[0] : null;
    }

    /** Verifica se o usuário pode ver o chamado. */
    public static function visivelPara(array $chamado, array $usuario): bool
    {
        return $usuario['perfil'] !== 'CLIENTE' || (int) $chamado['id_cliente'] === (int) $usuario['id_cliente'];
    }

    /** Abre um chamado: protocolo, prazos de SLA e histórico em uma transação. */
    public static function abrir(array $d, array $usuario, ?array $anexo = null): array
    {
        $db = self::db();
        $prioridade = Catalogo::prioridade((int) $d['id_prioridade']);
        $agora = new DateTimeImmutable();
        $prazos = ServicoSla::padrao()->calcularPrazos($agora, (int) $prioridade['min_resposta'], (int) $prioridade['min_solucao']);
        $db->beginTransaction();
        try {
            $protocolo = ServicoProtocolo::proximo($db);
            self::executar('INSERT INTO chamado (protocolo, id_cliente, id_solicitante, id_categoria, id_prioridade, titulo,
                              descricao, aberto_em, prazo_resposta, prazo_solucao) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                $protocolo, $d['id_cliente'], $usuario['id_usuario'], $d['id_categoria'], $d['id_prioridade'],
                $d['titulo'], $d['descricao'], $agora->format('Y-m-d H:i:s'),
                $prazos['prazo_resposta']->format('Y-m-d H:i:s'), $prazos['prazo_solucao']->format('Y-m-d H:i:s'),
            ]);
            $id = (int) $db->lastInsertId();
            self::registrarHistorico($id, (int) $usuario['id_usuario'], null, 'ABERTO');
            if ($anexo) {
                $idInt = Interacao::criar($id, (int) $usuario['id_usuario'], 'Anexo enviado na abertura do chamado.', false);
                Anexo::criar($idInt, $anexo);
            }
            $db->commit();
            return ['id' => $id, 'protocolo' => $protocolo];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function alterarStatus(array $chamado, string $novo, array $usuario): void
    {
        $sets = ['status = ?'];
        $p = [$novo];
        if ($novo === 'EM_ATENDIMENTO' && !$chamado['primeira_resposta_em']) $sets[] = 'primeira_resposta_em = NOW()';
        if ($novo === 'RESOLVIDO') $sets[] = 'resolvido_em = NOW()';
        if ($novo === 'FECHADO') $sets[] = 'fechado_em = NOW()';
        if ($novo === 'EM_ATENDIMENTO' && $chamado['status'] === 'RESOLVIDO') $sets[] = 'resolvido_em = NULL';
        $p[] = $chamado['id_chamado'];
        self::executar('UPDATE chamado SET ' . implode(', ', $sets) . ' WHERE id_chamado = ?', $p);
        self::registrarHistorico((int) $chamado['id_chamado'], (int) $usuario['id_usuario'], $chamado['status'], $novo);
    }

    public static function assumir(array $chamado, array $tecnico): void
    {
        self::executar('UPDATE chamado SET id_tecnico = ? WHERE id_chamado = ?', [$tecnico['id_usuario'], $chamado['id_chamado']]);
        if ($chamado['status'] === 'ABERTO') self::alterarStatus($chamado, 'EM_ATENDIMENTO', $tecnico);
    }

    public static function marcarPrimeiraResposta(int $id): void
    {
        self::executar('UPDATE chamado SET primeira_resposta_em = NOW() WHERE id_chamado = ? AND primeira_resposta_em IS NULL', [$id]);
    }

    public static function avaliar(array $chamado, int $nota, string $comentario, array $usuario): void
    {
        self::executar('UPDATE chamado SET nota_avaliacao = ?, comentario_avaliacao = ? WHERE id_chamado = ?',
            [$nota, $comentario ?: null, $chamado['id_chamado']]);
        self::alterarStatus($chamado, 'FECHADO', $usuario);
    }

    public static function historico(int $id): array
    {
        return self::todos('SELECT h.*, u.nome FROM historico_status h JOIN usuario u ON u.id_usuario = h.id_usuario
                            WHERE h.id_chamado = ? ORDER BY h.alterado_em, h.id_historico', [$id]);
    }

    private static function registrarHistorico(int $id, int $usuario, ?string $de, string $para): void
    {
        self::executar('INSERT INTO historico_status (id_chamado, id_usuario, status_anterior, status_novo) VALUES (?,?,?,?)',
            [$id, $usuario, $de, $para]);
    }

    /** Indicadores do painel. */
    public static function indicadores(array $usuario): array
    {
        $lista = self::listar($usuario, []);
        $i = ['ATIVOS' => 0, 'NO_PRAZO' => 0, 'EM_RISCO' => 0, 'VIOLADO' => 0, 'RESOLVIDOS_MES' => 0, 'NOTA_MEDIA' => null];
        $notas = [];
        $inicioMes = (new DateTimeImmutable('first day of this month'))->setTime(0, 0);
        foreach ($lista as $c) {
            if ($c['situacao_sla'] !== 'ENCERRADO') {
                $i['ATIVOS']++;
                $i[$c['situacao_sla']]++;
            }
            if ($c['resolvido_em'] && new DateTimeImmutable($c['resolvido_em']) >= $inicioMes) $i['RESOLVIDOS_MES']++;
            if ($c['nota_avaliacao']) $notas[] = (int) $c['nota_avaliacao'];
        }
        if ($notas) $i['NOTA_MEDIA'] = round(array_sum($notas) / count($notas), 1);
        return ['indicadores' => $i, 'lista' => $lista];
    }

    private static function comSituacao(array $linhas): array
    {
        $sla = ServicoSla::padrao();
        foreach ($linhas as &$c) {
            $c['situacao_sla'] = $sla->situacao($c['status'], new DateTimeImmutable($c['prazo_solucao']), (int) $c['min_solucao']);
        }
        return $linhas;
    }
}
