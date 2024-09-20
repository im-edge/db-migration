<?php

namespace IMEdge\DbMigration;

class PostgreSqlHelper extends CommonDbHelper
{
    public function listTables(): array
    {
        // Shamelessly stolen from ZF1
        // TODO: use a better query with joins instead of subqueries
        $sql = 'SELECT c.relname AS table_name '
            . ' FROM pg_class c, pg_user u'
            . " WHERE c.relowner = u.usesysid AND c.relkind = 'r'"
            . " AND NOT EXISTS (SELECT 1 FROM pg_views WHERE viewname = c.relname)"
            . " AND c.relname !~ '^(pg_|sql_)'"
            . ' UNION'
            . ' SELECT c.relname AS table_name'
            . ' FROM pg_class c'
            . " WHERE c.relkind = 'r'"
            . ' AND NOT EXISTS (SELECT 1 FROM pg_views WHERE viewname = c.relname)'
            . ' AND NOT EXISTS (SELECT 1 FROM pg_user WHERE usesysid = c.relowner)'
            . " AND c.relname !~ '^pg_'";

        return $this->fetchCol($sql);
    }
}
