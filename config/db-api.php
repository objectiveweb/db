<?php

declare(strict_types=1);

return [
    'authorize' => static fn (array $context, string $action, string $database, ?string $table): bool => true,
];
