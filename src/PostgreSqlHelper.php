<?php

namespace IMEdge\DbMigration;

class PostgreSqlHelper extends CommonDbHelper
{
    public function listTables(): array
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE"
            . " table_schema = 'public' AND table_type= 'BASE TABLE'"
            . " ORDER BY table_name";

        // Useless cast, but makes phpstan happy
        return array_map(fn ($value) => (string) $value, $this->fetchCol($sql));
    }
}
