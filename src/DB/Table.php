<?php

declare(strict_types=1);

namespace Objectiveweb\DB;

use Objectiveweb\DB;
use Objectiveweb\DB\Exception\InvalidQueryException;
use Objectiveweb\DB\Exception\NotFoundException;
use ReflectionClass;

class Table
{
    protected DB $db;
    protected ?string $table = null;
    protected ?ReflectionClass $modelClass = null;

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
            ], $params);
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

        $queryParams['join'] = $params['join'] ?? $this->params['join'];
        $queryParams['group'] = $params['group'] ?? $this->params['group'];

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
        $id = $this->db->insert((string) $this->table, $this->normalizeCreateData($data));

        return $id ? [(string) $this->params['pk'] => $id] : null;
    }

    /** @param int|string|array<string,mixed> $key @param array<string,mixed>|Model $data @return array<string,int> */
    public function update(int|string|array $key, array|Model $data): array
    {
        if (!is_array($key)) {
            $key = [(string) $this->params['pk'] => $key];
        }

        return ['updated' => $this->db->update((string) $this->table, $this->normalizeUpdateData($data), $key)];
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

}
