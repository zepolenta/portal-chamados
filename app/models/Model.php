<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/** Classe base dos Models: atalhos para consultas preparadas. */
abstract class Model
{
    protected static function db(): PDO
    {
        return Database::pdo();
    }

    protected static function todos(string $sql, array $params = []): array
    {
        $st = self::db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    protected static function um(string $sql, array $params = []): ?array
    {
        $st = self::db()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    protected static function executar(string $sql, array $params = []): int
    {
        $st = self::db()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }
}
