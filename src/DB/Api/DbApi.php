<?php

declare(strict_types=1);

namespace Objectiveweb\DB\Api;

use Objectiveweb\Router;

/** Framework-facing REST adapter for the database browser service. */
final class DbApi
{
    /** @var callable(array<string,mixed>,string,string,?string):bool */
    private $authorize;
    private DbApiService $service;

    /** @param callable(array<string,mixed>,string,string,?string):bool $authorize */
    public function __construct(private DatabaseRegistry $registry, callable $authorize)
    {
        $this->authorize = $authorize;
        $this->service = new DbApiService($registry);
    }

    public function register(Router $router, string $prefix = '/api'): void
    {
        $database = '([A-Za-z][A-Za-z0-9_-]*)';
        $table = '([A-Za-z][A-Za-z0-9_]*)';
        $id = '([^/]+)';
        $router->GET($prefix . '/?', fn (array $query): array => $this->databases($query));
        $router->GET($prefix . '/' . $database . '/?', fn (string $db, array $query): array => $this->tables($db, $query));
        $router->GET($prefix . '/' . $database . '/' . $table . '/schema/?', fn (string $db, string $table, array $query): array => $this->schema($db, $table, $query));
        $router->GET($prefix . '/' . $database . '/' . $table . '/?', fn (string $db, string $table, array $query): array => $this->rows($db, $table, $query));
        $router->POST($prefix . '/' . $database . '/' . $table . '/?', fn (string $db, string $table, array $body): array => $this->create($db, $table, $body));
        $router->GET($prefix . '/' . $database . '/' . $table . '/' . $id . '/?', fn (string $db, string $table, string $id, array $query): array => $this->row($db, $table, $id, $query));
        $router->route('PATCH ' . $prefix . '/' . $database . '/' . $table . '/' . $id . '/?', fn (string $db, string $table, string $id): array => $this->update($db, $table, $id, Router::parse_post_body()));
        $router->DELETE($prefix . '/' . $database . '/' . $table . '/' . $id . '/?', fn (string $db, string $table, string $id, array $query): array => $this->delete($db, $table, $id, $query));
    }

    /** @return list<array{name:string}> */
    public function databases(array $query = []): array
    {
        $this->allow('list-databases', '', null, $query);
        return $this->service->databases();
    }

    /** @return list<array<string,mixed>> */
    public function tables(string $database, array $query = []): array
    {
        $this->allow('list-tables', $database, null, $query);
        return $this->service->tables($database);
    }

    /** @return array<string,mixed> */
    public function schema(string $database, string $table, array $query = []): array
    {
        $this->allow('read-schema', $database, $table, $query);
        return $this->service->schema($database, $table);
    }

    /** @return array{data:list<array<string,mixed>>,total:int,offset:int,limit:int} */
    public function rows(string $database, string $table, array $query = []): array
    {
        $this->allow('list-rows', $database, $table, $query);
        return $this->service->rows($database, $table, $query);
    }

    /** @return array<string,mixed> */
    public function row(string $database, string $table, string $id, array $query = []): array
    {
        $this->allow('read-row', $database, $table, $query);
        return $this->service->row($database, $table, $id);
    }

    /** @return array<string,mixed> */
    public function create(string $database, string $table, array $body): array
    {
        $this->allow('create-row', $database, $table, []);
        $created = $this->service->create($database, $table, $body);
        if (!headers_sent()) http_response_code(201);
        return $created;
    }

    /** @return array<string,mixed> */
    public function update(string $database, string $table, string $id, array $body): array
    {
        $this->allow('update-row', $database, $table, []);
        return $this->service->update($database, $table, $id, $body);
    }

    /** @return array{deleted:int} */
    public function delete(string $database, string $table, string $id, array $query = []): array
    {
        $this->allow('delete-row', $database, $table, $query);
        return $this->service->delete($database, $table, $id);
    }

    private function allow(string $action, string $database, ?string $table, array $query): void
    {
        $context = ['server' => $_SERVER, 'query' => $query];
        if (!(($this->authorize)($context, $action, $database, $table))) throw new ApiException('Forbidden', 403);
    }
}

