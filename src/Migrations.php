<?php

namespace IMEdge\DbMigration;

use DirectoryIterator;
use Exception;
use IMEdge\PDO\PDO;
use InvalidArgumentException;
use RuntimeException;

class Migrations
{
    protected string $dbType;

    public function __construct(
        protected PDO $db,
        protected string $schemaDirectory,
        protected string $componentName,
        protected string $tableName = 'schema_migration'
    ) {
        $dbFamily = $db->getDriverFamily();
        $this->dbType = match ($dbFamily) {
            'mysql', 'pgsql' => $dbFamily,
            default => throw new InvalidArgumentException(sprintf(
                'Migrations are currently supported for MySQL/MariaDB and PostgreSQL only, got %s',
                $this->db->getDriverName()
            )),
        };
    }

    public function getLastMigrationNumber(): int
    {
        try {
            $query = 'SELECT MAX(m.schema_version) AS schema_version'
                . '  FROM m.' . $this->tableName
                . ' WHERE component_name = ?';

            return (int) $this->db->fetchOne($query, [$this->componentName]);
        } catch (Exception) {
            return 0;
        }
    }

    public function hasAnyTable(): bool
    {
        return count($this->db->listTables()) > 0;
    }

    public function hasTable(string $tableName): bool
    {
        return in_array($tableName, $this->db->listTables());
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
}
