<?php

declare(strict_types=1);

namespace Objectiveweb\Controller;

use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;

final class DbRowsController extends AbstractDbApiController
{
    public function __construct(DbApiService $service, DbApiAuthorizer $authorizer, private string $database, private string $table)
    {
        parent::__construct($service, $authorizer);
    }

    /** @return array{data:list<array<string,mixed>>,total:int,offset:int,limit:int} */
    public function index(array $query = []): array
    {
        $query = $this->query($query);
        $this->authorizer->authorize('list-rows', $this->database, $this->table, $query);
        return $this->service->rows($this->database, $this->table, $query);
    }

    /** @return array<string,mixed> */
    public function post(array $body): array
    {
        $this->authorizer->authorize('create-row', $this->database, $this->table);
        $created = $this->service->create($this->database, $this->table, $this->body($body));
        if (!headers_sent()) http_response_code(201);
        return $created;
    }
}

