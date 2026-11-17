<?php
namespace App\Models;

final class Usuario extends Model
{
    public const PERFIS = ['CLIENTE' => 'Cliente', 'TECNICO' => 'Técnico', 'ADMIN' => 'Administrador'];

    public static function porEmail(string $email): ?array
    {
        return self::um('SELECT * FROM usuario WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public static function porId(int $id): ?array
    {
        return self::um('SELECT id_usuario, id_cliente, nome, email, perfil, ativo FROM usuario WHERE id_usuario = ?', [$id]);
    }

    public static function listar(): array
    {
        return self::todos('SELECT u.id_usuario, u.nome, u.email, u.perfil, u.ativo, u.ultimo_acesso, c.nome AS cliente
                            FROM usuario u LEFT JOIN cliente c ON c.id_cliente = u.id_cliente ORDER BY u.perfil, u.nome');
    }

    public static function tecnicos(): array
    {
        return self::todos("SELECT id_usuario, nome FROM usuario WHERE perfil IN ('TECNICO','ADMIN') AND ativo = 1 ORDER BY nome");
    }

    public static function emailEmUso(string $email, ?int $ignorarId = null): bool
    {
        return (bool) self::um('SELECT 1 FROM usuario WHERE email = ? AND id_usuario <> ?', [mb_strtolower($email), $ignorarId ?? 0]);
    }

    public static function criar(array $d): int
    {
        self::executar('INSERT INTO usuario (id_cliente, nome, email, senha_hash, perfil) VALUES (?, ?, ?, ?, ?)', [
            $d['perfil'] === 'CLIENTE' ? $d['id_cliente'] : null, $d['nome'], mb_strtolower($d['email']),
            password_hash($d['senha'], PASSWORD_DEFAULT), $d['perfil'],
        ]);
        return (int) self::db()->lastInsertId();
    }

    public static function atualizar(int $id, array $d): void
    {
        self::executar('UPDATE usuario SET id_cliente = ?, nome = ?, email = ?, perfil = ?, ativo = ? WHERE id_usuario = ?', [
            $d['perfil'] === 'CLIENTE' ? $d['id_cliente'] : null, $d['nome'], mb_strtolower($d['email']), $d['perfil'], $d['ativo'], $id,
        ]);
        if (!empty($d['senha'])) {
            self::executar('UPDATE usuario SET senha_hash = ? WHERE id_usuario = ?', [password_hash($d['senha'], PASSWORD_DEFAULT), $id]);
        }
    }

    public static function registrarAcesso(int $id): void
    {
        self::executar('UPDATE usuario SET ultimo_acesso = NOW() WHERE id_usuario = ?', [$id]);
    }
}
