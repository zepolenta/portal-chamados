<?php
namespace App\Core;

use PDO;

/** Conexão única (Singleton) com o MySQL via PDO. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = App::config('db');
            $dsn = "mysql:host={$c['host']};port={$c['porta']};dbname={$c['nome']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $c['user'], $c['senha'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Mantém o banco no mesmo fuso da aplicação
            self::$pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        }
        return self::$pdo;
    }
}
