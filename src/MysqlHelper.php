<?php

namespace IMEdge\DbMigration;

class MysqlHelper extends CommonDbHelper
{
    public function listTables(): array
    {
        // Useless cast, but makes phpstan happy
        return array_map(fn ($value) => (string) $value, $this->fetchCol('SHOW TABLES'));
    }
}
