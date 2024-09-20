<?php

namespace IMEdge\DbMigration;

class MysqlHelper extends CommonDbHelper
{
    public function listTables(): array
    {
        return $this->fetchCol('SHOW TABLES');
    }
}
