<?php
namespace App\Models;

final class Interacao extends Model
{
    public static function criar(int $idChamado, int $idUsuario, string $mensagem, bool $interna): int
    {
        self::executar('INSERT INTO interacao (id_chamado, id_usuario, mensagem, interna) VALUES (?,?,?,?)',
            [$idChamado, $idUsuario, $mensagem, (int) $interna]);
        return (int) self::db()->lastInsertId();
    }

    /** Linha do tempo do chamado; notas internas só para a equipe (RN07). */
    public static function doChamado(int $idChamado, bool $incluirInternas): array
    {
        $sql = 'SELECT i.*, u.nome, u.perfil FROM interacao i JOIN usuario u ON u.id_usuario = i.id_usuario
                WHERE i.id_chamado = ?' . ($incluirInternas ? '' : ' AND i.interna = 0') . ' ORDER BY i.criado_em, i.id_interacao';
        $itens = self::todos($sql, [$idChamado]);
        if (!$itens) return [];
        $ids = array_column($itens, 'id_interacao');
        $marc = implode(',', array_fill(0, count($ids), '?'));
        $anexos = self::todos("SELECT * FROM anexo WHERE id_interacao IN ($marc) ORDER BY id_anexo", $ids);
        foreach ($itens as &$it) {
            $it['anexos'] = array_values(array_filter($anexos, fn($a) => $a['id_interacao'] == $it['id_interacao']));
        }
        return $itens;
    }
}
