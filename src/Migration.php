<?php

namespace IMEdge\DbMigration;

use Exception;
use PDO;
use RuntimeException;

class Migration
{
    public function __construct(
        protected int $version,
        protected string $sql
    ) {
    }

    public function apply(PDO $db): void
    {
        // TODO: this is fragile and depends on accordingly written schema files:
        $sql = preg_replace('/-- .*$/m', '', $this->sql);
        if ($sql === null) {
            throw new RuntimeException('Got invalid SQL in migration ' . $this->version);
        }
        $queries = preg_split(
            '/[\n\s\t]*;[\n\s\t]+/s',
            $sql,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (empty($queries)) {
            throw new RuntimeException(sprintf(
                'Migration %d has no queries',
                $this->version
            ));
        }

        try {
            foreach ($queries as $query) {
                if (preg_match('/^(?:OPTIMIZE|EXECUTE) /i', $query)) {
                    $db->query($query);
                } else {
                    $db->exec($query);
                }
            }
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(
                'Migration %d failed (%s) while running %s',
                $this->version,
                $e->getMessage(),
                $query
            ));
        }
    }
}
