<?php

declare(strict_types=1);

namespace Objectiveweb\DB;

use Closure;
use Objectiveweb\DB;
use Objectiveweb\DB\Exception\InvalidQueryException;
use Objectiveweb\DB\Exception\NotFoundException;
use ReflectionClass;

class Table
{
    protected DB $db;
    protected ?string $table = null;
    protected ?ReflectionClass $modelClass = null;
    protected ?Closure $whereFieldResolver = null;

    /** @var array<string,mixed>|null */
    protected ?array $params = null;

    /** @param array<string,mixed> $params */
    public function __construct(DB $db, ?string $table = null, array $params = [])
    {
        $this->db = $db;

        if ($this->table === null) {
            $this->table = $table;
        }

        if ($this->params === null) {
            $this->params = array_merge([
                'pk' => 'id',
                'join' => [],
                'group' => null,
                'fields' => ['*'],
                'model' => null,
                'extends' => null,
            ], $params);
        }

        if (!is_array($this->params['join'])) {
            throw new InvalidQueryException('Invalid join configuration');
        }

        if ($this->hasInheritance()) {
            $baseTable = (string) $this->resolveInheritanceTable();
            $pk = (string) $this->params['pk'];
            $baseKey = $this->resolveInheritanceKey();
            $baseJoinDef = [$baseTable => sprintf('%s.%s = %s.%s', (string) $this->table, $pk, $baseTable, $baseKey)];

            // Preserve numeric join entries while allowing string-key legacy joins to be merged.
            $this->params['join'] = array_merge($this->params['join'], $baseJoinDef);

            $baseLookup = [];
            foreach ($this->resolveInheritanceFields() as $field) {
                if (!is_string($field) || trim($field) === '') {
                    throw new InvalidQueryException('Invalid extends.fields configuration');
                }

                $baseLookup[$field] = true;
            }

            $mainTable = (string) $this->table;
            $this->whereFieldResolver = static fn (string $field): string => (isset($baseLookup[$field]) ? $baseTable : $mainTable) . '.' . $field;
        }

        if($this->params['model']) {
            if (!is_subclass_of($this->params['model'], Model::class)) {
                throw new InvalidQueryException("Invalid model class: {$this->params['model']}");
            }

            $this->modelClass = new \ReflectionClass($this->params['model']);
        }
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    private function parseParams(array $params): array
    {
        $queryParams = [];

        if (empty($params['fields'])) {
            $params['fields'] = $this->params['fields'];
        }

        if (!is_array($params['fields'])) {
            $params['fields'] = array_map('trim', explode(',', (string) $params['fields']));
        }

        $queryParams['fields'] = $params['fields'];

        if (!empty($params['sort'])) {
            $queryParams['order'] = $params['sort'];
        }

        if (!empty($params['range'])) {
            if (!is_array($params['range'])) {
                $params['range'] = (array) json_decode((string) $params['range'], true);
            }
            $queryParams['offset'] = (int) $params['range'][0];
            $queryParams['limit'] = (int) $params['range'][1] - (int) $params['range'][0] + 1;
        } else {
            $queryParams['offset'] = 0;
        }

        if(empty($params['join'])) {
          $queryParams['join'] = $this->params['join'];
        }
        else {
          if (!is_array($params['join'])) {
            throw new InvalidQueryException('Invalid join configuration');
          }

          $queryParams['join'] = array_merge($this->params['join'], $params['join']);

        }

        $queryParams['group'] = $params['group'] ?? $this->params['group'];
        $queryParams['where_field_resolver'] = $this->whereFieldResolver;

        return $queryParams;
    }

    /** @param array<string,mixed> $filter @param array<string,mixed> $params */
    public function select(array $filter = [], array $params = []): Collection
    {
        if (!empty($params['filter'])) {
            if (!is_array($params['filter'])) {
                $decoded = json_decode((string) $params['filter'], true);
                $where = array_merge($filter, is_array($decoded) ? $decoded : []);
            } else {
                $where = array_merge($filter, $params['filter']);
            }
        } else {
            $where = $filter;
        }

        $queryParams = $this->parseParams($params);
        $query = $this->db->select((string) $this->table, $where, $queryParams);

        $countParams = [
            'join' => $queryParams['join'] ?? [],
            'group' => $queryParams['group'] ?? null,
            'where_field_resolver' => $queryParams['where_field_resolver'] ?? null,
        ];

        $rowsCount = $this->db->count((string) $this->table, $where, $countParams);
        $data = $this->hydrateRows($query->all());

        return new Collection(
            $data,
            (int) ($queryParams['offset'] ?? 0),
            (int) ($queryParams['offset'] ?? 0) + count($data) - 1,
            $rowsCount
        );
    }

    /** @param mixed $key @param array<string,mixed> $params @return Collection|array<string,mixed>|object */
    public function get(mixed $key = null, array $params = []): mixed
    {
        if ($key === null || is_array($key)) {
            return $this->select(is_array($key) ? $key : [], $params);
        }

        $params = $this->parseParams($params);
        $where = ["{$this->table}.{$this->params['pk']}" => $key];
        $query = $this->db->select((string) $this->table, $where, array_merge($params, ['limit' => 1]));

        $record = $query->fetch();
        if ($record === false) {
            throw new NotFoundException('Record not found', 404);
        }

        return $this->hydrateRow($record);
    }

    /** @param array<string,mixed>|Model $data */
    public function insert(array|Model $data): ?array
    {
        $payload = $this->normalizeCreateData($data);
        if (!$this->hasInheritance()) {
            $id = $this->db->insert((string) $this->table, $payload);

            if (!$id) {
                $pk = (string) $this->params['pk'];
                if (array_key_exists($pk, $payload) && $payload[$pk] !== null && $payload[$pk] !== '') {
                    $id = (string) $payload[$pk];
                }
            }

            return $id ? [(string) $this->params['pk'] => $id] : null;
        }

        $pk = (string) $this->params['pk'];
        $baseTable = (string) $this->resolveInheritanceTable();
        $baseKey = $this->resolveInheritanceKey();
        [$basePayload, $mainPayload] = $this->splitPayloadByInheritance($payload);
        $explicitId = null;

        if (array_key_exists($pk, $payload) && $payload[$pk] !== null && $payload[$pk] !== '') {
            $explicitId = (string) $payload[$pk];
            $basePayload[$baseKey] = $payload[$pk];
            $mainPayload[$pk] = $payload[$pk];
        }

        $id = $this->db->transaction(function (DB $db) use ($baseTable, $basePayload, $mainPayload, $explicitId, $pk, $baseKey): ?string {
            if ($basePayload === [] && $mainPayload === []) {
                throw new InvalidQueryException('Nothing to INSERT');
            }

            $baseInsert = $basePayload;
            $mainInsert = $mainPayload;
            $id = $explicitId;
            $insertedBase = false;
            $insertedMain = false;

            if ($id !== null) {
                $baseInsert[$baseKey] = $baseInsert[$baseKey] ?? $id;
                $mainInsert[$pk] = $mainInsert[$pk] ?? $id;
            }

            if ($id === null && $baseInsert !== [] && ($baseKey === $pk || (array_key_exists($baseKey, $baseInsert) && $baseInsert[$baseKey] !== null && $baseInsert[$baseKey] !== ''))) {
                $id = $db->insert($baseTable, $baseInsert);
                $insertedBase = true;
                if (($id === null || $id === '') && array_key_exists($baseKey, $baseInsert) && $baseInsert[$baseKey] !== null && $baseInsert[$baseKey] !== '') {
                    $id = (string) $baseInsert[$baseKey];
                }
            }

            if ($id === null && $mainInsert !== []) {
                $id = $db->insert((string) $this->table, $mainInsert);
                $insertedMain = true;
                if (($id === null || $id === '') && array_key_exists($pk, $mainInsert) && $mainInsert[$pk] !== null && $mainInsert[$pk] !== '') {
                    $id = (string) $mainInsert[$pk];
                }
            }

            if ($id === null || $id === '') {
                if (array_key_exists($baseKey, $baseInsert) && $baseInsert[$baseKey] !== null && $baseInsert[$baseKey] !== '') {
                    $id = (string) $baseInsert[$baseKey];
                } elseif (array_key_exists($pk, $mainInsert) && $mainInsert[$pk] !== null && $mainInsert[$pk] !== '') {
                    $id = (string) $mainInsert[$pk];
                } else {
                    return null;
                }
            }

            $baseInsert[$baseKey] = $baseInsert[$baseKey] ?? $id;
            $mainInsert[$pk] = $mainInsert[$pk] ?? $id;

            if (!$insertedBase && $baseInsert !== []) {
                $db->insert($baseTable, $baseInsert);
            }

            if (!$insertedMain && $mainInsert !== []) {
                $db->insert((string) $this->table, $mainInsert);
            }

            return (string) $id;
        });

        return $id ? [$pk => $id] : null;
    }

    /** @param int|string|array<string,mixed> $key @param array<string,mixed>|Model $data @return array<string,int> */
    public function update(int|string|array $key, array|Model $data): array
    {
        if (!is_array($key)) {
            $key = [(string) $this->params['pk'] => $key];
        }

        $payload = $this->normalizeUpdateData($data);

        if (!$this->hasInheritance()) {
            return ['updated' => $this->db->update((string) $this->table, $payload, $key)];
        }

        $pk = (string) $this->params['pk'];
        $baseTable = (string) $this->resolveInheritanceTable();
        $baseKey = $this->resolveInheritanceKey();
        [$basePayload, $mainPayload] = $this->splitPayloadByInheritance($payload);
        if ($basePayload === [] && $mainPayload === []) {
            throw new InvalidQueryException('Nothing to UPDATE');
        }

        $updated = $this->db->transaction(function (DB $db) use ($pk, $baseKey, $key, $basePayload, $mainPayload, $baseTable): int {
            $selectParams = $this->parseParams([
                'fields' => ["{$this->table}.{$pk}"],
            ]);
            $ids = array_map(
                fn (array $row): mixed => $row[$pk] ?? null,
                $db->select((string) $this->table, $key, $selectParams)->all()
            );
            $ids = array_values(array_filter($ids, fn (mixed $id): bool => $id !== null && $id !== ''));

            if ($ids === []) {
                return 0;
            }

            $mainUpdated = 0;
            $baseUpdated = 0;

            if ($mainPayload !== []) {
                $mainUpdated = $db->update((string) $this->table, $mainPayload, [$pk => $ids]);
            }

            if ($basePayload !== []) {
                $baseUpdated = $db->update($baseTable, $basePayload, [$baseKey => $ids]);
            }

            return max($mainUpdated, $baseUpdated);
        });

        return ['updated' => $updated];
    }

    /** @param int|string|array<string,mixed> $key */
    public function delete(int|string|array $key): int
    {
        if (!is_array($key)) {
            $key = [(string) $this->params['pk'] => $key];
        }

        return $this->db->delete((string) $this->table, $key);
    }

    public function findBy(string $key, mixed $value): Collection
    {
        return $this->select([], [
            'filter' => [
                $key => $value,
            ],
        ]);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>|object> */
    private function hydrateRows(array $rows): array
    {
        if (!$this->modelClass) {
            return $rows;
        }

        return array_map(fn (array $row): object => $this->modelClass->newInstance($row), $rows);
    }

    /** @param array<string,mixed> $row */
    private function hydrateRow(array $row): array|object
    {
        if (!$this->modelClass) {
            return $row;
        }

        return $this->modelClass->newInstance($row);
    }

    /** @param array<string,mixed>|Model $data @return array<string,mixed> */
    private function normalizeCreateData(array|Model $data): array
    {
        $payload = $data instanceof Model ? $data->toArray() : $data;

        if (!$this->modelClass) {
            return $payload;
        }

        $modelClass = (string) $this->params['model'];
        return $modelClass::normalizeForCreate($payload);
    }

    /** @param array<string,mixed>|Model $data @return array<string,mixed> */
    private function normalizeUpdateData(array|Model $data): array
    {
        $payload = $data instanceof Model ? $data->toArray() : $data;

        if (!$this->modelClass) {
            return $payload;
        }

        $modelClass = (string) $this->params['model'];
        return $modelClass::normalizeForUpdate($payload);
    }

    private function hasInheritance(): bool
    {
        return $this->resolveInheritanceTable() !== null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function splitPayloadByInheritance(array $payload): array
    {
        $baseFields = $this->resolveInheritanceFields();
        if (!is_array($baseFields)) {
            throw new InvalidQueryException('Invalid extends.fields configuration');
        }

        $baseLookup = [];
        foreach ($baseFields as $field) {
            if (!is_string($field) || trim($field) === '') {
                throw new InvalidQueryException('Invalid extends.fields configuration');
            }

            $baseLookup[$field] = true;
        }

        $basePayload = [];
        $mainPayload = [];

        foreach ($payload as $field => $value) {
            if (isset($baseLookup[(string) $field])) {
                $basePayload[(string) $field] = $value;
                continue;
            }

            $mainPayload[(string) $field] = $value;
        }

        return [$basePayload, $mainPayload];
    }

    private function resolveInheritanceTable(): ?string
    {
        $extends = $this->params['extends'] ?? null;
        if ($extends === null) {
            return null;
        }

        if (!is_array($extends)) {
            throw new InvalidQueryException('Invalid extends configuration');
        }

        $table = $extends['table'] ?? null;
        if (!is_string($table) || trim($table) === '') {
            throw new InvalidQueryException('Invalid extends.table configuration');
        }

        return trim($table);
    }

    /** @return list<string> */
    private function resolveInheritanceFields(): array
    {
        $extends = $this->params['extends'] ?? null;
        if ($extends === null) {
            return [];
        }

        if (!is_array($extends)) {
            throw new InvalidQueryException('Invalid extends configuration');
        }

        $fields = $extends['fields'] ?? [];
        if (!is_array($fields)) {
            throw new InvalidQueryException('Invalid extends.fields configuration');
        }

        return $fields;
    }

    private function resolveInheritanceKey(): string
    {
        $extends = $this->params['extends'] ?? null;
        if ($extends === null) {
            return 'id';
        }

        if (!is_array($extends)) {
            throw new InvalidQueryException('Invalid extends configuration');
        }

        $key = $extends['key'] ?? 'id';
        if (!is_string($key) || trim($key) === '') {
            throw new InvalidQueryException('Invalid extends.key configuration');
        }

        return trim($key);
    }

}
