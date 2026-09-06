<?php

declare(strict_types=1);

namespace Objectiveweb\DB\Api;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table as DoctrineTable;
use Doctrine\DBAL\Types\Type;
use Objectiveweb\DB;
use Objectiveweb\DB\Table;

/** Maps public database names to host-configured DB instances. */
final class DatabaseRegistry
{
    /** @var array<string,DB> */
    private array $databases;

    /** @param array<string,DB> $databases */
    public function __construct(array $databases)
    {
        foreach ($databases as $name => $database) {
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) || !$database instanceof DB) {
                throw new \InvalidArgumentException('Database registry entries must be named DB instances.');
            }
        }
        $this->databases = $databases;
    }

    /** @return list<array{name:string}> */
    public function all(): array
    {
        return array_map(static fn (string $name): array => ['name' => $name], array_keys($this->databases));
    }

    public function database(string $name): DB
    {
        if (!isset($this->databases[$name])) throw new ApiException('Database not found', 404);
        return $this->databases[$name];
    }

    /** @return list<array<string,mixed>> */
    public function tables(string $database): array
    {
        $db = $this->database($database);
        $result = [];
        foreach ($db->schemaManager()->listTableNames() as $name) {
            $schema = $db->schemaManager()->introspectTable($name);
            $primaryKey = $schema->getPrimaryKey();
            $columns = $primaryKey ? $primaryKey->getColumns() : [];
            $result[] = ['name' => $name, 'primaryKey' => count($columns) === 1 ? $columns[0] : null, 'writable' => count($columns) === 1];
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function schema(string $database, string $table): array
    {
        $schema = $this->doctrineTable($database, $table);
        $primaryKey = $schema->getPrimaryKey();
        $primaryColumns = $primaryKey ? $primaryKey->getColumns() : [];
        $columns = [];
        foreach ($schema->getColumns() as $column) $columns[] = $this->column($column, in_array($column->getName(), $primaryColumns, true));
        return ['name' => $schema->getName(), 'primaryKey' => count($primaryColumns) === 1 ? $primaryColumns[0] : null, 'writable' => count($primaryColumns) === 1, 'columns' => $columns];
    }

    public function table(string $database, string $table): Table
    {
        $schema = $this->schema($database, $table);
        if (!$schema['writable']) throw new ApiException('This table does not have a single-column primary key', 405);
        return $this->database($database)->table($table, ['pk' => $schema['primaryKey']]);
    }

    private function doctrineTable(string $database, string $table): DoctrineTable
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $table)) throw new ApiException('Table not found', 404);
        try {
            return $this->database($database)->schemaManager()->introspectTable($table);
        } catch (\Throwable $exception) {
            throw new ApiException('Table not found', 404, $exception);
        }
    }

    /** @return array<string,mixed> */
    private function column(Column $column, bool $primary): array
    {
        $type = $column->getType();
        $name = method_exists($type, 'getName') ? $type->getName() : Type::lookupName($type);
        return ['name' => $column->getName(), 'type' => $name, 'nullable' => !$column->getNotnull(), 'default' => $column->getDefault(), 'autoIncrement' => $column->getAutoincrement(), 'primary' => $primary, 'writable' => !$column->getAutoincrement()];
    }
}
