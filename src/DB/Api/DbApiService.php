<?php

declare(strict_types=1);

namespace Objectiveweb\DB\Api;

/** Domain service for the database browser's schema and row operations. */
final class DbApiService
{
    public function __construct(private DatabaseRegistry $registry)
    {
    }

    /** @return list<array{name:string}> */
    public function databases(): array
    {
        return $this->registry->all();
    }

    /** @return list<array<string,mixed>> */
    public function tables(string $database): array
    {
        return $this->registry->tables($database);
    }

    /** @return array<string,mixed> */
    public function schema(string $database, string $table): array
    {
        return $this->registry->schema($database, $table);
    }

    /** @return array{data:list<array<string,mixed>>,total:int,offset:int,limit:int} */
    public function rows(string $database, string $table, array $query): array
    {
        $schema = $this->schema($database, $table);
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 25)));
        $params = ['range' => [$offset, $offset + $limit - 1]];
        if (isset($query['sort'])) $params['sort'] = $this->sort((string) $query['sort'], $schema);

        $collection = $this->registry->table($database, $table)->select(
            $this->filter($query['filter'] ?? null, $schema),
            $params
        );

        return ['data' => $collection->data(), 'total' => $collection->total(), 'offset' => $offset, 'limit' => $limit];
    }

    /** @return array<string,mixed> */
    public function row(string $database, string $table, string $id): array
    {
        return (array) $this->registry->table($database, $table)->get($id);
    }

    /** @return array<string,mixed> */
    public function create(string $database, string $table, array $body): array
    {
        $id = $this->registry->table($database, $table)->insert($this->payload($body, $this->schema($database, $table)));
        if ($id === null) throw new ApiException('Row was not created', 500);
        return $id;
    }

    /** @return array<string,mixed> */
    public function update(string $database, string $table, string $id, array $body): array
    {
        $schema = $this->schema($database, $table);
        $this->registry->table($database, $table)->update($id, $this->payload($body, $schema));
        return (array) $this->registry->table($database, $table)->get($id);
    }

    /** @return array{deleted:int} */
    public function delete(string $database, string $table, string $id): array
    {
        return ['deleted' => $this->registry->table($database, $table)->delete($id)];
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function filter(mixed $input, array $schema): array
    {
        if ($input === null || $input === '') return [];
        $filter = is_array($input) ? $input : json_decode((string) $input, true);
        if (!is_array($filter) || array_is_list($filter)) throw new ApiException('filter must be a JSON object', 400);
        $this->assertColumns(array_keys($filter), $schema);
        return $filter;
    }

    /** @param array<string,mixed> $schema @return list<array{0:string,1:string}> */
    private function sort(string $input, array $schema): array
    {
        $sort = [];
        foreach (array_filter(explode(',', $input)) as $part) {
            [$field, $direction] = array_pad(explode(':', $part, 2), 2, 'asc');
            $this->assertColumns([$field], $schema);
            $direction = strtolower($direction);
            if (!in_array($direction, ['asc', 'desc'], true)) throw new ApiException('Invalid sort direction', 400);
            $sort[] = [$field, $direction];
        }
        return $sort;
    }

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function payload(array $payload, array $schema): array
    {
        if ($payload === []) throw new ApiException('Request body may not be empty', 400);
        $writable = array_column(array_filter($schema['columns'], static fn (array $column): bool => $column['writable']), 'name');
        foreach (array_keys($payload) as $field) if (!in_array($field, $writable, true)) throw new ApiException('Field is not writable: ' . $field, 422);
        return $payload;
    }

    /** @param list<string> $fields @param array<string,mixed> $schema */
    private function assertColumns(array $fields, array $schema): void
    {
        $valid = array_column($schema['columns'], 'name');
        foreach ($fields as $field) if (!in_array($field, $valid, true)) throw new ApiException('Unknown column: ' . $field, 400);
    }
}

