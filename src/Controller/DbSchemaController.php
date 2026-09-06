<?php

declare(strict_types=1);

namespace Objectiveweb\Controller;

use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;

final class DbSchemaController extends AbstractDbApiController
{
    public function __construct(DbApiService $service, DbApiAuthorizer $authorizer, private string $database, private string $table)
    {
        parent::__construct($service, $authorizer);
    }

    /** @return array<string,mixed> */
    public function index(array $query = []): array
    {
        $this->authorizer->authorize('read-schema', $this->database, $this->table, $this->query($query));
        return $this->service->schema($this->database, $this->table);
    }
}

