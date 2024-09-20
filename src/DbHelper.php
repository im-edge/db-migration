<?php

namespace IMEdge\DbMigration;

use PDO;

interface DbHelper
{
    public function __construct(PDO $db);
    /**
     * @return string[]
     */
    public function listTables(): array;

    /**
     * @param array<int, string|int|float|null> $bind Data to bind into SELECT placeholders
     */
    public function fetchOne(string $sql, array $bind = []): string|int|null;

    /**
     * Fetches all SQL result rows as an array of key-value pairs.
     *
     * The first column is the key, the second column is the
     * value.
     *
     * @param string $sql An SQL SELECT statement.
     * @param array<int, string|int|float|null> $bind Data to bind into SELECT placeholders
     * @return array<int, string|int|float|null>
     */
    public function fetchPairs(string $sql, array $bind = []): array;
}
