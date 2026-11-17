<?php
namespace App\Services;

/** Validação de CPF e CNPJ pelos dígitos verificadores. */
final class ValidadorDocumento
{
    public static function somenteDigitos(string $doc): string
    {
        return preg_replace('/\D/', '', $doc);
    }

    public static function cpfValido(string $cpf): bool
    {
        $cpf = self::somenteDigitos($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) $soma += (int) $cpf[$i] * (($t + 1) - $i);
            $dv = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $dv) return false;
        }
        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        $cnpj = self::somenteDigitos($cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
        $pesos = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ($pesos as $k => $p) {
            $soma = 0;
            foreach ($p as $i => $peso) $soma += (int) $cnpj[$i] * $peso;
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[12 + $k] !== $dv) return false;
        }
        return true;
    }

    public static function valido(string $tipoPessoa, string $doc): bool
    {
        return $tipoPessoa === 'PF' ? self::cpfValido($doc) : self::cnpjValido($doc);
    }
}
