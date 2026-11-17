<?php
namespace App\Models;

/** Tabelas de domínio: categorias e prioridades/SLA. */
final class Catalogo extends Model
{
    public static function categorias(): array
    {
        return self::todos('SELECT id_categoria, nome FROM categoria WHERE ativo = 1 ORDER BY id_categoria');
    }

    public static function prioridades(): array
    {
        return self::todos('SELECT * FROM prioridade_sla ORDER BY id_prioridade');
    }

    public static function prioridade(int $id): ?array
    {
        return self::um('SELECT * FROM prioridade_sla WHERE id_prioridade = ?', [$id]);
    }

    public static function categoriaExiste(int $id): bool
    {
        return (bool) self::um('SELECT 1 FROM categoria WHERE id_categoria = ? AND ativo = 1', [$id]);
    }
}
