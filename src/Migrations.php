<?php

namespace IMEdge\DbMigration;

use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class Migrations
{
    protected const DB_TYPE_MYSQL = 'mysql';
    protected const DB_TYPE_POSTGRESQL = 'pgsql';

    protected string $dbType;
    protected DbHelper $dbHelper;

    public function __construct(
        protected PDO $db,
        protected string $schemaDirectory,
        protected string $componentName,
        protected string $tableName = 'schema_migration'
    ) {
        $driverName = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        assert(is_string($driverName));
        switch ($driverName) {
            case self::DB_TYPE_MYSQL:
                $this->dbType = self::DB_TYPE_MYSQL;
                $this->dbHelper = new MysqlHelper($db);
                break;
            case self::DB_TYPE_POSTGRESQL:
                $this->dbType = self::DB_TYPE_POSTGRESQL;
                $this->dbHelper = new PostgreSqlHelper($db);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'Migrations are currently supported for MySQL/MariaDB and PostgreSQL only, got %s',
                    $driverName
                ));
        }
    }

    public function getLastMigrationNumber(): int
    {
        try {
            $query = 'SELECT MAX(m.schema_version) AS schema_version'
                . '  FROM m.' . $this->tableName
                . ' WHERE component_name = ?';

            return (int) $this->dbHelper->fetchOne($query, [$this->componentName]);
        } catch (Exception) {
            return 0;
        }
    }

    public function hasAnyTable(): bool
    {
        return count($this->dbHelper->listTables()) > 0;
    }

    public function hasTable(string $tableName): bool
    {
        return in_array($tableName, $this->dbHelper->listTables());
    }

    public function hasMigrationsTable(): bool
    {
        return $this->hasTable($this->tableName);
    }

    public function hasSchema(): bool
    {
        return $this->listPendingMigrations() !== [0];
    }

    public function hasPendingMigrations(): bool
    {
        return $this->countPendingMigrations() > 0;
    }

    public function countPendingMigrations(): int
    {
        return count($this->listPendingMigrations());
    }

    /**
     * @return Migration[]
     */
    public function getPendingMigrations(): array
    {
        $migrations = [];
        foreach ($this->listPendingMigrations() as $version) {
            $migrations[] = new Migration(
                $version,
                $this->loadMigrationFile($version)
            );
        }

        return $migrations;
    }

    public function applyPendingMigrations(): void
    {
        foreach ($this->getPendingMigrations() as $migration) {
            $migration->apply($this->db);
        }
    }

    /**
     * @return int[]
     */
    public function listPendingMigrations(): array
    {
        $lastMigration = $this->getLastMigrationNumber();
        if ($lastMigration === 0) {
            return [0];
        }

        return $this->listMigrationsAfter($this->getLastMigrationNumber());
    }

    /**
     * @return int[]
     */
    public function listAllMigrations(): array
    {
        $dir = $this->getMigrationsDirectory();
        $versions = [];

        if (! is_readable($dir)) {
            return $versions;
        }

        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filename = $file->getFilename();
            if (preg_match('/^upgrade_(\d+)\.sql$/', $filename, $match)) {
                $versions[] = (int) $match[1];
            }
        }
        sort($versions);

        return $versions;
    }

    public function loadMigrationFile(int $version): string
    {
        if ($version === 0) {
            $filename = $this->getFullSchemaFile();
        } else {
            $filename = sprintf(
                '%s/upgrade_%d.sql',
                $this->getMigrationsDirectory(),
                $version
            );
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            throw new RuntimeException('Failed to load migration file ' . $filename);
        }

        return $content;
    }

    /**
     * @return int[]
     */
    protected function listMigrationsAfter(int $version): array
    {
        $filtered = [];
        foreach ($this->listAllMigrations() as $available) {
            if ($available > $version) {
                $filtered[] = $available;
            }
        }

        return $filtered;
    }

    public function getSchemaDirectory(?string $subDirectory = null): string
    {
        if ($subDirectory === null) {
            return $this->schemaDirectory;
        }

        return $this->schemaDirectory . '/' . ltrim($subDirectory, '/');
    }

    public function getMigrationsDirectory(): string
    {
        return $this->getSchemaDirectory($this->dbType . '-migrations');
    }

    protected function getFullSchemaFile(): string
    {
        return $this->getSchemaDirectory($this->dbType . '.sql');
    }

    /**
     * Still unused
     */
    protected function createMigrationsTable(): void
    {
        if ($this->dbType === self::DB_TYPE_POSTGRESQL) {
            $create = /** @lang text */
                <<<SQL

CREATE TABLE $this->tableName (
  schema_version SMALLINT NOT NULL,
  component_name VARCHAR(64) NOT NULL,
  migration_time TIMESTAMP WITH TIME ZONE NOT NULL,
  PRIMARY KEY (component_name, schema_version)
);

SQL;
        } else {
            $create = /** @lang text */
                <<<SQL
CREATE TABLE $this->tableName (
  schema_version SMALLINT UNSIGNED NOT NULL,
  component_name VARCHAR(64) NOT NULL,
  migration_time DATETIME NOT NULL,
  PRIMARY KEY (component_name, schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;
SQL;
        }
        $this->db->exec($create);
    }
}
