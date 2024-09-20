<?php

namespace IMEdge\DbMigration;

use PDO;
use PDOException;

abstract class CommonDbHelper implements DbHelper
{
    public function __construct(protected readonly PDO $db)
    {
    }

    /**
     * @param array<int, string|int|float|null> $bind
     * @return array<int, string|int|float|null>
     */
    public function fetchCol(string $sql, array $bind = []): array
    {
        $stmt = $this->db->prepare($sql, $bind);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function fetchPairs(string $sql, array $bind = []): array
    {
        $stmt = $this->db->prepare($sql, $bind);
        $data = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            assert(is_array($row));
            $data[$row[0]] = $row[1];
        }

        return $data;
    }

    /**
     * @throws PDOException|NoResultError
     */
    public function fetchOne(string $sql, array $bind = []): string|int|null
    {
        $stmt = $this->db->prepare($sql, $bind);
        $result = $stmt->fetchColumn(0);
        if ($result === false) {
            throw new NoResultError('Trying to fetch one, but there is no result');
        }

        return $result;
    }
}
