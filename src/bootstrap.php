<?php

declare(strict_types=1);

define('APPROOT', dirname(__DIR__));
require APPROOT . '/vendor/autoload.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Api\DatabaseRegistry;
use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;
use Objectiveweb\Router;

if (session_status() === PHP_SESSION_NONE) session_start();

$dsn = getenv('DB_API_DSN');
if (!is_string($dsn) || $dsn === '') throw new RuntimeException('DB_API_DSN must be configured.');

$databaseName = getenv('DB_API_DATABASE') ?: 'app';
$database = new DB($dsn, getenv('DB_API_USERNAME') ?: null, getenv('DB_API_PASSWORD') ?: '');
$registry = new DatabaseRegistry([$databaseName => $database]);
$authConfig = APPROOT . '/config/db-api.php';
$settings = is_file($authConfig) ? require $authConfig : [];
$authorize = $settings['authorize'] ?? static fn (array $context, string $action, string $database, ?string $table): bool => false;
if (!is_callable($authorize)) throw new RuntimeException('config/db-api.php must provide an authorize callable.');

$app = new Router(APPROOT);
$app->addRule(DB::class, ['shared' => true, 'constructParams' => [$dsn, getenv('DB_API_USERNAME') ?: null, getenv('DB_API_PASSWORD') ?: '']]);
$app->addRule(DbApiService::class, ['shared' => true, 'constructParams' => [$registry]]);
$app->addRule(DbApiAuthorizer::class, ['shared' => true, 'constructParams' => [$authorize]]);

