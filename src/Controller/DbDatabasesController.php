<?php

declare(strict_types=1);

namespace Objectiveweb\Controller;

final class DbDatabasesController extends AbstractDbApiController
{
    /** @return list<array{name:string}> */
    public function index(array $query = []): array
    {
        $this->authorizer->authorize('list-databases', '', null, $this->query($query));
        return $this->service->databases();
    }
}

