<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Objectiveweb\Controller\DbDatabasesController;
use Objectiveweb\Controller\DbRowController;
use Objectiveweb\Controller\DbRowsController;
use Objectiveweb\Controller\DbSchemaController;
use Objectiveweb\Controller\DbTablesController;

$database = '([A-Za-z][A-Za-z0-9_-]*)';
$table = '([A-Za-z][A-Za-z0-9_]*)';
$id = '([^/]+)';

$app->controller('/api/' . $database . '/' . $table . '/schema', DbSchemaController::class);
$app->controller('/api/' . $database . '/' . $table . '/' . $id, DbRowController::class);
$app->controller('/api/' . $database . '/' . $table, DbRowsController::class);
$app->controller('/api/' . $database, DbTablesController::class);
$app->controller('/api', DbDatabasesController::class);

exit('not found');

