<?php
namespace App\Models;

final class Cliente extends Model
{
    public static function listar(?string $busca = null): array
    {
        $sql = 'SELECT c.*, ct.plano, ct.horas_mes,
                       (SELECT COUNT(*) FROM chamado ch WHERE ch.id_cliente = c.id_cliente
                          AND ch.status NOT IN (\'FECHADO\',\'CANCELADO\',\'RESOLVIDO\')) AS abertos
                FROM cliente c
                LEFT JOIN contrato ct ON ct.id_cliente = c.id_cliente AND ct.ativo = 1';
        $p = [];
        if ($busca) {
            $sql .= ' WHERE c.nome LIKE ? OR c.cpf_cnpj LIKE ?';
            $p = ["%$busca%", '%' . preg_replace('/\D/', '', $busca) . '%'];
        }
        return self::todos($sql . ' ORDER BY c.nome', $p);
    }

    public static function ativos(): array
    {
        return self::todos('SELECT id_cliente, nome FROM cliente WHERE ativo = 1 ORDER BY nome');
    }

    public static function porId(int $id): ?array
    {
        return self::um('SELECT c.*, ct.id_contrato, ct.plano, ct.horas_mes, ct.inicio_vigencia, ct.fim_vigencia
                         FROM cliente c LEFT JOIN contrato ct ON ct.id_cliente = c.id_cliente AND ct.ativo = 1
                         WHERE c.id_cliente = ?', [$id]);
    }

    public static function documentoEmUso(string $doc, ?int $ignorarId = null): bool
    {
        return (bool) self::um('SELECT 1 FROM cliente WHERE cpf_cnpj = ? AND id_cliente <> ?', [$doc, $ignorarId ?? 0]);
    }

    /** Grava cliente e, se mensalista, o contrato vigente (transação). */
    public static function salvar(?int $id, array $d): int
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $campos = [$d['tipo_pessoa'], $d['nome'], $d['cpf_cnpj'], $d['email'], $d['telefone'] ?: null, $d['modalidade'], $d['ativo']];
            if ($id) {
                self::executar('UPDATE cliente SET tipo_pessoa=?, nome=?, cpf_cnpj=?, email=?, telefone=?, modalidade=?, ativo=? WHERE id_cliente=?', [...$campos, $id]);
            } else {
                self::executar('INSERT INTO cliente (tipo_pessoa, nome, cpf_cnpj, email, telefone, modalidade, ativo) VALUES (?,?,?,?,?,?,?)', $campos);
                $id = (int) $db->lastInsertId();
            }
            if ($d['modalidade'] === 'MENSALISTA') {
                $atual = self::um('SELECT id_contrato FROM contrato WHERE id_cliente = ? AND ativo = 1', [$id]);
                $ct = [$d['plano'], (int) $d['horas_mes'], $d['inicio_vigencia'], $d['fim_vigencia'] ?: null];
                if ($atual) {
                    self::executar('UPDATE contrato SET plano=?, horas_mes=?, inicio_vigencia=?, fim_vigencia=? WHERE id_contrato=?', [...$ct, $atual['id_contrato']]);
                } else {
                    self::executar('INSERT INTO contrato (plano, horas_mes, inicio_vigencia, fim_vigencia, id_cliente) VALUES (?,?,?,?,?)', [...$ct, $id]);
                }
            } else {
                self::executar('UPDATE contrato SET ativo = 0 WHERE id_cliente = ?', [$id]);
            }
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
