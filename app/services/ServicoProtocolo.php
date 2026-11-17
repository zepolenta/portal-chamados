<?php
namespace App\Services;

use PDO;

/** Gera protocolos no formato PL + ano + 6 dígitos (ex.: PL2026000123). */
final class ServicoProtocolo
{
    /** Deve ser chamado dentro de uma transação (usa FOR UPDATE para evitar duplicidade). */
    public static function proximo(PDO $pdo, ?int $ano = null): string
    {
        $ano ??= (int) date('Y');
        $prefixo = 'PL' . $ano;
        $st = $pdo->prepare('SELECT protocolo FROM chamado WHERE protocolo LIKE ? ORDER BY protocolo DESC LIMIT 1 FOR UPDATE');
        $st->execute([$prefixo . '%']);
        $ultimo = $st->fetchColumn();
        $seq = $ultimo ? ((int) substr($ultimo, 6)) + 1 : 1;
        return $prefixo . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
