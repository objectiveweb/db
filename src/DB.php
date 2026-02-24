<?php

declare(strict_types=1);

namespace Objectiveweb;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Objectiveweb\DB\Expr;
use Objectiveweb\DB\Exception\InvalidQueryException;
use Objectiveweb\DB\Exception\TransactionException;
use Objectiveweb\DB\Query;
use Objectiveweb\DB\Table;

class DB
{
    private Connection $connection;
    private bool $debug = false;

    public ?string $error = null;

    private string $prefix;

    public function __construct(string|array $dsn, ?string $username = null, string $password = '', array $options = [])
    {

        $this->prefix = isset($options['prefix']) ? (string) $options['prefix'] : '';
        unset($options['prefix']);

        if (is_array($dsn)) {
            $params = self::fromParsedUrl($dsn, $username, $password, $options);
        } else {
            $params = self::fromDsnString($dsn, $username, $password, $options);
        }

        $this->connection = DriverManager::getConnection($params);
    }

    public function query(string $sql, mixed ...$args): Query
    {
        if ($args !== []) {
            $sql = sprintf($sql, ...$args);
        }

        $query = new Query($this->connection, $sql);
        $query->debugSql = $sql;

        if ($this->debug) {
            error_log($sql);
        }

        return $query;
    }

    public function beginTransaction(): bool
    {
        try {
            $this->connection->beginTransaction();
            return true;
        } catch (\Throwable $e) {
            throw new TransactionException('Cannot begin transaction', 500, $e);
        }
    }

    public function rollBack(): bool
    {
        try {
            $this->connection->rollBack();
            return true;
        } catch (\Throwable $e) {
            throw new TransactionException('Cannot rollback transaction', 500, $e);
        }
    }

    public function commit(): bool
    {
        try {
            $this->connection->commit();
            return true;
        } catch (\Throwable $e) {
            throw new TransactionException('Cannot commit transaction', 500, $e);
        }
    }

    public function transaction(callable $callable): mixed
    {
        $this->beginTransaction();

        try {
            $ret = $callable($this);

            $this->commit();
            return $ret;
        } catch (\Throwable $ex) {
            try {
                $this->rollBack();
            } catch (TransactionException $rollbackError) {
                throw new TransactionException('Transaction rollback failed after error', 500, $rollbackError);
            }
            throw $ex;
        }
    }

    /**
     * @param array<string,mixed>|string|null $where
     * @param array<string,mixed> $params
     */
    public function select(string $table, array|string|null $where = null, array $params = []): Query
    {
        $defaults = [
            'fields' => ['*'],
            'group' => null,
            'order' => null,
            'limit' => null,
            'offset' => 0,
            'join' => [],
            'lock' => null,
        ];

        $params = array_merge($defaults, $params);

        $tableAlias = $this->assertIdentifier($table);
        $tableName = $this->prefix . $tableAlias;

        $fields = $this->compileFields($params['fields']);
        [$joinSql, $joinBindings] = $this->compileJoin((array) $params['join'], $tableAlias);
        [$whereSql, $whereBindings] = $this->buildWhereClause($where);

        $sql = sprintf(
            'SELECT %s FROM %s %s%s%s',
            implode(', ', $fields),
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($tableAlias),
            $joinSql !== '' ? ' ' . $joinSql : '',
            $whereSql !== '' ? ' WHERE ' . $whereSql : ''
        );

        if (!empty($params['group'])) {
            $sql .= ' GROUP BY ' . $this->compileGroup($params['group']);
        }

        if (!empty($params['order'])) {
            $sql .= ' ORDER BY ' . $this->compileOrder($params['order']);
        }

        if ($params['limit'] !== null) {
            $sql .= sprintf(' LIMIT %d OFFSET %d', (int) $params['limit'], (int) $params['offset']);
        }

        $sql .= $this->compileLockClause($params['lock'] ?? null);

        $query = $this->query($sql);
        $query->exec(array_merge($joinBindings, $whereBindings));

        return $query;
    }

    /**
     * @param array<string,mixed>|string|null $where
     * @param array<string,mixed> $params
     */
    public function count(string $table, array|string|null $where = null, array $params = []): int
    {
        $params = array_merge([
            'join' => [],
            'group' => null,
        ], $params);

        $tableAlias = $this->assertIdentifier($table);
        $tableName = $this->prefix . $tableAlias;

        [$joinSql, $joinBindings] = $this->compileJoin((array) $params['join'], $tableAlias);
        [$whereSql, $whereBindings] = $this->buildWhereClause($where);

        $base = sprintf(
            ' FROM %s %s%s%s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($tableAlias),
            $joinSql !== '' ? ' ' . $joinSql : '',
            $whereSql !== '' ? ' WHERE ' . $whereSql : ''
        );

        $bindings = array_merge($joinBindings, $whereBindings);

        if (!empty($params['group'])) {
            $groupSql = $this->compileGroup($params['group']);
            $sql = 'SELECT COUNT(*) AS count FROM (SELECT 1' . $base . ' GROUP BY ' . $groupSql . ') ow_count';
        } else {
            $sql = 'SELECT COUNT(*) AS count' . $base;
        }

        $result = $this->query($sql);
        $result->exec($bindings);
        $countRow = $result->fetch();

        return isset($countRow['count']) ? (int) $countRow['count'] : 0;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): ?string
    {
        if ($data === []) {
            throw new InvalidQueryException('Nothing to INSERT');
        }

        $tableName = $this->prefix . $this->assertIdentifier($table);
        $columns = [];
        $placeholders = [];
        $bindings = [];

        foreach ($data as $field => $value) {
            $column = $this->assertIdentifier((string) $field);
            $columns[] = $this->quoteIdentifier($column);
            $placeholders[] = ':' . $column;
            $bindings[$column] = is_bool($value) ? (int) $value : $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($tableName),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $affected = $this->query($sql)->exec($bindings);

        return $affected === 0 ? null : (string) $this->connection->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|string|null $where
     */
    public function update(string $table, array $data, array|string|null $where = null, ?int $limit = null): int
    {
        if ($data === []) {
            throw new InvalidQueryException('Nothing to UPDATE');
        }

        [$whereSql, $whereBindings] = $this->buildWhereClause($where);
        if ($whereSql === '') {
            throw new InvalidQueryException('Unsafe UPDATE without WHERE clause');
        }

        $tableName = $this->prefix . $this->assertIdentifier($table);
        $changes = [];
        $bindings = $whereBindings;

        foreach ($data as $key => $value) {
            $field = $this->assertIdentifier((string) $key);
            $placeholder = 'update_' . $field;
            $changes[] = sprintf('%s = :%s', $this->quoteIdentifier($field), $placeholder);
            $bindings[$placeholder] = is_bool($value) ? (int) $value : $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s%s',
            $this->quoteIdentifier($tableName),
            implode(', ', $changes),
            $whereSql,
            $limit !== null ? sprintf(' LIMIT %d', $limit) : ''
        );

        return $this->query($sql)->exec($bindings);
    }

    /** @param array<string,mixed>|string|null $where */
    public function delete(string $table, array|string|null $where): int
    {
        [$whereSql, $whereBindings] = $this->buildWhereClause($where);
        if ($whereSql === '') {
            throw new InvalidQueryException('Unsafe DELETE without WHERE clause');
        }

        $tableName = $this->prefix . $this->assertIdentifier($table);

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($tableName),
            $whereSql
        );

        return $this->query($sql)->exec($whereBindings);
    }

    public function debug(bool $status = true): void
    {
        $this->debug = $status;
    }

    /** @param array<string,mixed> $params */
    public function table(string $table, array $params = ['pk' => 'id']): Table
    {
        if (class_exists($table) && is_subclass_of($table, Table::class)) {
            return new $table($this);
        }

        return new Table($this, $table, $params);
    }

    /**
     * @param array<string,mixed> $array
     * @param list<string>|string $validKeys
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    public static function array_cleanup(array $array, array|string $validKeys = [], array $defaults = []): array
    {
        $keys = is_array($validKeys) ? $validKeys : [$validKeys];
        $cleanArray = array_intersect_key($array, array_flip($keys));
        return array_merge($defaults, $cleanArray);
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * @param array<string,mixed>|string|null $args
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhereClause(array|string|null $args = null, string $glue = 'AND'): array
    {
        if ($args === null || $args === '') {
            return ['', []];
        }

        if (is_string($args)) {
            throw new InvalidQueryException('Raw WHERE string is disabled. Use array conditions.');
        }

        $cond = [];
        $bindings = [];

        foreach ($args as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidQueryException('Invalid WHERE key');
            }

            $not = false;
            if ($key[0] === '!') {
                $not = true;
                $key = substr($key, 1);
            }

            $field = $this->quoteIdentifierPath($this->assertIdentifier($key));
            $baseName = 'where_' . preg_replace('/[^A-Za-z0-9_]/', '_', $key) . '_' . count($bindings);

            if (is_array($value)) {
                if ($value === []) {
                    $cond[] = $not ? '1=1' : '1=0';
                    continue;
                }

                $list = [];
                foreach (array_values($value) as $i => $item) {
                    $name = $baseName . '_' . $i;
                    $list[] = ':' . $name;
                    $bindings[$name] = is_bool($item) ? (int) $item : $item;
                }

                $cond[] = sprintf('%s %sIN (%s)', $field, $not ? 'NOT ' : '', implode(', ', $list));
                continue;
            }

            if ($value === null) {
                $cond[] = sprintf('%s IS %sNULL', $field, $not ? 'NOT ' : '');
                continue;
            }

            $operator = '=';
            if (is_string($value) && strpos($value, '%') !== false) {
                $operator = $not ? 'NOT LIKE' : 'LIKE';
            } elseif ($not) {
                $operator = '<>';
            }

            $cond[] = sprintf('%s %s :%s', $field, $operator, $baseName);
            $bindings[$baseName] = is_bool($value) ? (int) $value : $value;
        }

        return [implode(' ' . $glue . ' ', $cond), $bindings];
    }

    /** @param list<string|Expr>|string $fields */
    private function compileFields(array|string $fields): array
    {
        $fields = is_array($fields) ? $fields : array_map('trim', explode(',', $fields));
        $compiled = [];

        foreach ($fields as $alias => $field) {
            if (!is_string($field) && !$field instanceof Expr) {
                throw new InvalidQueryException('Invalid SELECT field');
            }

            $rendered = $this->compileFieldToken($field);
            if (!is_int($alias)) {
                $rendered .= ' AS ' . $this->quoteSingleIdentifier($this->assertIdentifier((string) $alias));
            }
            $compiled[] = $rendered;
        }

        if ($compiled === []) {
            throw new InvalidQueryException('At least one field is required');
        }

        return $compiled;
    }

    private function compileFieldToken(string|Expr $field): string
    {
        if ($field instanceof Expr) {
            $sql = trim($field->toSql());
            if ($sql === '') {
                throw new InvalidQueryException('Invalid SELECT expression');
            }
            return $sql;
        }

        $field = trim($field);

        if ($field === '*') {
            return '*';
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.\*$/', $field) === 1) {
            [$table] = explode('.', $field, 2);
            return $this->quoteIdentifier($table) . '.*';
        }

        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\((\*|[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\)$/i', $field, $matches) === 1) {
            $function = strtoupper($matches[1]);
            $target = $matches[2] === '*' ? '*' : $this->quoteIdentifierPath($this->assertIdentifier($matches[2]));
            return sprintf('%s(%s)', $function, $target);
        }

        if (preg_match(
            '/^GROUP_CONCAT\(\s*(DISTINCT\s+)?([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\s*\)$/i',
            $field,
            $matches
        ) === 1) {
            $distinct = isset($matches[1]) && trim($matches[1]) !== '' ? 'DISTINCT ' : '';
            $target = $this->quoteIdentifierPath($this->assertIdentifier($matches[2]));
            return sprintf('GROUP_CONCAT(%s%s)', $distinct, $target);
        }

        if (preg_match(
            '/^COUNT\(\s*CASE\s+WHEN\s+([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\s+IS\s+(NOT\s+)?NULL\s+THEN\s+(-?\d+)(?:\s+ELSE\s+(-?\d+))?\s+END\s*\)$/i',
            $field,
            $matches
        ) === 1) {
            $target = $this->quoteIdentifierPath($this->assertIdentifier($matches[1]));
            $not = isset($matches[2]) && trim($matches[2]) !== '' ? 'NOT ' : '';
            $thenValue = $matches[3];
            $elseClause = isset($matches[4]) && $matches[4] !== '' ? ' ELSE ' . $matches[4] : '';

            return sprintf(
                'COUNT(CASE WHEN %s IS %sNULL THEN %s%s END)',
                $target,
                $not,
                $thenValue,
                $elseClause
            );
        }

        if (preg_match('/^COALESCE\((.+)\)$/i', $field, $matches) === 1) {
            $arguments = array_map('trim', explode(',', $matches[1]));
            if (count($arguments) < 2) {
                throw new InvalidQueryException('COALESCE expects at least two identifiers');
            }

            $quoted = [];
            foreach ($arguments as $argument) {
                if ($argument === '') {
                    throw new InvalidQueryException('COALESCE expects valid identifier arguments');
                }
                $quoted[] = $this->quoteIdentifierPath($this->assertIdentifier($argument));
            }

            return sprintf('COALESCE(%s)', implode(', ', $quoted));
        }

        return $this->quoteIdentifierPath($this->assertIdentifier($field));
    }

    /**
     * Join formats:
     * - Legacy map: ['left:table alias' => 'alias.id = base.ref'].
     *   Supported prefixes: inner:, left:, right:, full:, cross:
     *   If no prefix is provided, plain JOIN is used (database default join behavior).
     * - Structured list:
     *   [
     *     ['type' => 'left', 'table' => 'people', 'alias' => 'p', 'on' => 'p.id = t.person_id'],
     *     ['type' => 'cross', 'table' => 'calendar', 'alias' => 'c'],
     *   ]
     *
     * @param array<int|string,mixed> $join
     * @return array{0:string,1:array<string,mixed>}
     */
    private function compileJoin(array $join, string $baseAlias): array
    {
        if ($join === []) {
            return ['', []];
        }

        $parts = [];

        foreach ($join as $key => $value) {
            if (is_int($key)) {
                if (!is_array($value)) {
                    throw new InvalidQueryException('Raw JOIN strings are disabled. Use structured join arrays.');
                }

                $parts[] = $this->compileStructuredJoin($value);
                continue;
            }

            [$joinType, $tableDef] = $this->extractLegacyJoinType((string) $key);
            [$table, $alias] = $this->parseTableAlias(trim($tableDef));
            $condition = trim((string) $value);
            if ($condition === '' && $joinType !== 'CROSS JOIN') {
                throw new InvalidQueryException('Join condition cannot be empty');
            }

            $parts[] = $this->renderJoinClause($joinType, $table, $alias, $condition);
        }

        unset($baseAlias);
        return [implode(' ', $parts), []];
    }

    /** @param array<string,mixed> $join */
    private function compileStructuredJoin(array $join): string
    {
        if (!isset($join['table']) || !is_string($join['table']) || trim($join['table']) === '') {
            throw new InvalidQueryException('Structured join requires a non-empty table');
        }

        $joinType = $this->normalizeJoinType($join['type'] ?? 'inner');
        $table = $this->assertIdentifier(trim($join['table']));
        $alias = isset($join['alias']) && is_string($join['alias']) && $join['alias'] !== ''
            ? $this->assertIdentifier(trim($join['alias']))
            : $table;

        $condition = isset($join['on']) ? trim((string) $join['on']) : '';
        if ($joinType !== 'CROSS JOIN' && $condition === '') {
            throw new InvalidQueryException('Join condition cannot be empty');
        }

        return $this->renderJoinClause($joinType, $table, $alias, $condition);
    }

    /** @return array{0:string,1:string} */
    private function extractLegacyJoinType(string $tableDef): array
    {
        $tableDef = trim($tableDef);
        if ($tableDef === '') {
            throw new InvalidQueryException('Invalid table definition');
        }

        if (!str_contains($tableDef, ':')) {
            return ['JOIN', $tableDef];
        }

        [$type, $remainder] = explode(':', $tableDef, 2);
        $type = strtolower(trim($type));
        $remainder = trim($remainder);

        if ($remainder === '') {
            throw new InvalidQueryException('Invalid table definition');
        }

        if (!in_array($type, ['inner', 'left', 'right', 'full', 'cross'], true)) {
            return ['JOIN', $tableDef];
        }

        return [$this->normalizeJoinType($type), $remainder];
    }

    private function normalizeJoinType(mixed $type): string
    {
        if (!is_string($type) || trim($type) === '') {
            throw new InvalidQueryException('Invalid join type');
        }

        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'inner', 'inner join' => 'INNER JOIN',
            'join' => 'JOIN',
            'left', 'left join', 'left outer', 'left outer join' => 'LEFT JOIN',
            'right', 'right join', 'right outer', 'right outer join' => 'RIGHT JOIN',
            'full', 'full join', 'full outer', 'full outer join' => 'FULL OUTER JOIN',
            'cross', 'cross join' => 'CROSS JOIN',
            default => throw new InvalidQueryException('Unsupported join type'),
        };
    }

    private function renderJoinClause(string $joinType, string $table, string $alias, string $condition): string
    {
        $base = sprintf(
            '%s %s %s',
            $joinType,
            $this->quoteIdentifier($this->prefix . $table),
            $this->quoteIdentifier($alias)
        );

        if ($joinType === 'CROSS JOIN') {
            return $base;
        }

        return $base . ' ON ' . $condition;
    }

    private function compileGroup(mixed $group): string
    {
        if (is_string($group)) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $group)), fn (string $part): bool => $part !== ''));
        } elseif (is_array($group)) {
            $parts = [];
            foreach ($group as $part) {
                if (!is_string($part) || trim($part) === '') {
                    throw new InvalidQueryException('Invalid group value');
                }

                $parts[] = trim($part);
            }
        } else {
            throw new InvalidQueryException('Invalid group value');
        }

        if ($parts === []) {
            throw new InvalidQueryException('Invalid group value');
        }

        $compiled = array_map(
            fn (string $part): string => $this->quoteIdentifierPath($this->assertIdentifier($part)),
            $parts
        );

        return implode(', ', $compiled);
    }

    private function compileLockClause(mixed $lock): string
    {
        if ($lock === null || $lock === false || $lock === '') {
            return '';
        }

        if ($lock === true) {
            return ' FOR UPDATE';
        }

        if (!is_string($lock)) {
            throw new InvalidQueryException('Invalid lock clause');
        }

        $normalized = strtolower(trim($lock));
        return match ($normalized) {
            'update', 'for update' => ' FOR UPDATE',
            'share', 'for share' => ' FOR SHARE',
            default => throw new InvalidQueryException('Unsupported lock mode'),
        };
    }

    private function compileOrder(mixed $order): string
    {
        if (is_string($order)) {
            $pieces = array_map('trim', explode(',', $order));
            $rendered = [];
            foreach ($pieces as $piece) {
                if ($piece === '') {
                    continue;
                }
                $rendered[] = $this->compileOrderPiece($piece);
            }

            if ($rendered === []) {
                throw new InvalidQueryException('Invalid order clause');
            }

            return implode(', ', $rendered);
        }

        if (is_array($order)) {
            if ($order === []) {
                throw new InvalidQueryException('Invalid order clause');
            }

            if (
                count($order) === 2
                && isset($order[0], $order[1])
                && is_string($order[0])
                && is_string($order[1])
                && in_array(strtoupper(trim($order[1])), ['ASC', 'DESC'], true)
            ) {
                return $this->compileOrderPiece($order[0] . ' ' . $order[1]);
            }

            $rendered = [];
            foreach ($order as $piece) {
                if (is_array($piece)) {
                    if (
                        !isset($piece[0])
                        || !is_string($piece[0])
                        || (isset($piece[1]) && !is_string($piece[1]))
                    ) {
                        throw new InvalidQueryException('Invalid order clause');
                    }

                    $rendered[] = $this->compileOrderPiece(
                        isset($piece[1]) ? $piece[0] . ' ' . $piece[1] : $piece[0]
                    );
                    continue;
                }

                $rendered[] = $this->compileOrderPiece((string) $piece);
            }

            return implode(', ', $rendered);
        }

        throw new InvalidQueryException('Invalid order clause');
    }

    private function compileOrderPiece(string $piece): string
    {
        $trimmed = trim($piece);

        if (preg_match(
            '/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)(?:\s+IS\s+(NOT\s+)?NULL)?(?:\s+(ASC|DESC))?$/i',
            $trimmed,
            $matches
        ) !== 1) {
            throw new InvalidQueryException('Invalid order expression');
        }

        $field = $this->quoteIdentifierPath($this->assertIdentifier($matches[1]));
        $nullClause = '';
        if (stripos($trimmed, ' IS ') !== false) {
            $nullClause = isset($matches[2]) && trim((string) $matches[2]) !== '' ? ' IS NOT NULL' : ' IS NULL';
        }

        $dir = isset($matches[3]) ? ' ' . strtoupper($matches[3]) : '';

        return $field . $nullClause . $dir;
    }

    /** @return array{0:string,1:string} */
    private function parseTableAlias(string $tableDef): array
    {
        $parts = preg_split('/\s+/', trim($tableDef));
        if (!is_array($parts) || $parts === []) {
            throw new InvalidQueryException('Invalid table definition');
        }

        $table = $this->assertIdentifier($parts[0]);
        $alias = isset($parts[1]) ? $this->assertIdentifier($parts[1]) : $table;

        return [$table, $alias];
    }

    private function assertIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $identifier) !== 1) {
            throw new InvalidQueryException("Invalid identifier: {$identifier}");
        }

        return $identifier;
    }

    private function quoteIdentifierPath(string $identifier): string
    {
        $segments = explode('.', $identifier);
        $segments = array_map(fn (string $segment): string => $this->quoteIdentifier($segment), $segments);
        return implode('.', $segments);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    private function quoteSingleIdentifier(string $identifier): string
    {
        return $this->connection->quoteSingleIdentifier($identifier);
    }

    /**
     * @param array<string,mixed> $dsn
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function fromParsedUrl(array $dsn, ?string $username, string $password, array $options): array
    {
        $scheme = (string) ($dsn['scheme'] ?? 'mysql');
        $dbName = isset($dsn['dbname']) ? (string) $dsn['dbname'] : ltrim((string) ($dsn['path'] ?? ''), '/');

        $params = [
            'driver' => self::mapDriver($scheme),
            'host' => $dsn['host'] ?? '127.0.0.1',
            'dbname' => $dbName,
            'user' => $dsn['user'] ?? $username,
            'password' => $dsn['pass'] ?? $password,
        ];

        if (isset($dsn['port'])) {
            $params['port'] = (int) $dsn['port'];
        }

        return array_merge($params, $options);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function fromDsnString(string $dsn, ?string $username, string $password, array $options): array
    {
        if (strpos($dsn, ':') === false) {
            throw new InvalidQueryException('Invalid DSN format');
        }

        [$scheme, $rest] = explode(':', $dsn, 2);

        if ($scheme === 'sqlite') {
            $path = trim($rest);
            if (str_starts_with($path, 'dbname=')) {
                $path = substr($path, strlen('dbname='));
            }

            $params = [
                'driver' => 'pdo_sqlite',
                'path' => $path,
                'user' => $username,
                'password' => $password,
            ];

            return array_merge($params, $options);
        }

        $pairs = [];
        foreach (explode(';', $rest) as $chunk) {
            if ($chunk === '' || strpos($chunk, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $chunk, 2);
            $pairs[trim($key)] = trim($value);
        }

        $params = [
            'driver' => self::mapDriver($scheme),
            'host' => $pairs['host'] ?? '127.0.0.1',
            'dbname' => $pairs['dbname'] ?? '',
            'charset' => $pairs['charset'] ?? 'utf8',
            'user' => $username,
            'password' => $password,
        ];

        return array_merge($params, $options);
    }

    private static function mapDriver(string $scheme): string
    {
        return match ($scheme) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            'sqlsrv' => 'pdo_sqlsrv',
            default => throw new InvalidQueryException("Unsupported driver scheme: {$scheme}"),
        };
    }
}
