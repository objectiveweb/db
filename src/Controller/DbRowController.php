<?php

declare(strict_types=1);

namespace Objectiveweb\Controller;

use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;

final class DbRowController extends AbstractDbApiController
{
    public function __construct(DbApiService $service, DbApiAuthorizer $authorizer, private string $database, private string $table, private string $id)
    {
        parent::__construct($service, $authorizer);
    }

    /** @return array<string,mixed> */
    public function index(array $query = []): array
    {
        $this->authorizer->authorize('read-row', $this->database, $this->table, $this->query($query));
        return $this->service->row($this->database, $this->table, $this->id);
    }

    /** @return array<string,mixed> */
    public function patch(array $body): array
    {
        $this->authorizer->authorize('update-row', $this->database, $this->table);
        return $this->service->update($this->database, $this->table, $this->id, $this->body($body));
    }

    /** @return array{deleted:int} */
    public function delete(array $query = []): array
    {
        $this->authorizer->authorize('delete-row', $this->database, $this->table, $this->query($query));
        return $this->service->delete($this->database, $this->table, $this->id);
    }
}

