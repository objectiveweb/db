<?php

declare(strict_types=1);

namespace Objectiveweb\DB\Api;

/** Authorization boundary for database-browser operations. */
final class DbApiAuthorizer
{
    /** @param callable(array<string,mixed>,string,string,?string):bool $authorize */
    public function __construct(private $authorize)
    {
    }

    public function authorize(string $action, string $database, ?string $table, array $query = []): void
    {
        $context = ['server' => $_SERVER, 'query' => $query];
        if (!(($this->authorize)($context, $action, $database, $table))) throw new ApiException('Forbidden', 403);
    }
}

