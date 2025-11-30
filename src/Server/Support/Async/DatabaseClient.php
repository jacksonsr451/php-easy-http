<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Async;

use PDO;
use PDOException;

final class DatabaseClient
{
    public static function query(
        string $dsn,
        string $statement,
        array $params = [],
        ?string $username = null,
        ?string $password = null,
        array $options = []
    ): AwaitableInterface {
        return new Awaitable(static function () use ($dsn, $username, $password, $options, $statement, $params): array {
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare($statement);
            if ($stmt === false) {
                throw new PDOException('Unable to prepare statement.');
            }

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }
}
