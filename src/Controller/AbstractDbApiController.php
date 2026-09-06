<?php

declare(strict_types=1);

namespace Objectiveweb\Controller;

use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;

abstract class AbstractDbApiController
{
    public function __construct(
        protected DbApiService $service,
        protected DbApiAuthorizer $authorizer,
    ) {
    }

    /** @return array<string,mixed> */
    protected function query(array $query): array
    {
        return array_intersect_key($query, array_flip(['filter', 'sort', 'offset', 'limit']));
    }

    /** @return array<string,mixed> */
    protected function body(array $body): array
    {
        return array_filter($body, static fn (mixed $value, mixed $key): bool => is_string($key), ARRAY_FILTER_USE_BOTH);
    }
}

