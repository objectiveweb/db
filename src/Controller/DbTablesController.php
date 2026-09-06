<?php

declare(strict_types=1);

namespace Objectiveweb\Controller;

use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;

final class DbTablesController extends AbstractDbApiController
{
    public function __construct(DbApiService $service, DbApiAuthorizer $authorizer, private string $database)
    {
        parent::__construct($service, $authorizer);
    }

    /** @return list<array<string,mixed>> */
    public function index(array $query = []): array
    {
        $this->authorizer->authorize('list-tables', $this->database, null, $this->query($query));
        return $this->service->tables($this->database);
    }
}

