<?php

declare(strict_types=1);

namespace Objectiveweb\DB;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Objectiveweb\DB\Exception\InvalidQueryException;

class Query
{
    private Connection $connection;
    private string $sql;

    /** @var array<string,mixed> */
    private array $bindings = [];

    private ?Result $result = null;

    public ?string $debugSql = null;

    public function __construct(Connection $connection, string $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
    }

    public function bind(string $pos, mixed $value): self
    {
        $this->bindings[ltrim($pos, ':')] = $value;
        return $this;
    }

    /**
     * @param array<string,mixed>|null $bindings
     */
    public function exec(?array $bindings = null): int
    {
        $params = $this->normalizeBindings($bindings);
        $this->freeResult();

        if ($this->isResultSetQuery($this->sql)) {
            $this->result = $this->connection->executeQuery($this->sql, $params);
            return $this->result->rowCount();
        }

        return $this->connection->executeStatement($this->sql, $params);
    }

    /** @return array<string,mixed>|false */
    public function fetch(): array|false
    {
        if ($this->result === null) {
            return false;
        }

        $row = $this->result->fetchAssociative();
        return $row === false ? false : $row;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        if ($this->result === null) {
            return [];
        }

        return $this->result->fetchAllAssociative();
    }

    /** @return array<int|string,array<string,mixed>> */
    public function map(string $field): array
    {
        if ($this->result === null) {
            return [];
        }

        $mapped = [];

        while (($row = $this->result->fetchAssociative()) !== false) {
            if (!array_key_exists($field, $row)) {
                throw new InvalidQueryException("Invalid field {$field}");
            }

            $mapped[$row[$field]] = $row;
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed>|null $bindings
     * @return array<string,mixed>
     */
    private function normalizeBindings(?array $bindings): array
    {
        $params = $this->bindings;

        if ($bindings !== null) {
            foreach ($bindings as $key => $value) {
                $params[ltrim((string) $key, ':')] = $value;
            }
        }

        return $params;
    }

    private function isResultSetQuery(string $sql): bool
    {
        return (bool) preg_match('/^\s*(SELECT|SHOW|DESCRIBE|PRAGMA|WITH)\b/i', $sql);
    }

    private function freeResult(): void
    {
        if ($this->result !== null) {
            $this->result->free();
            $this->result = null;
        }
    }
}
