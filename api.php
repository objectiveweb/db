<?php 
require('vendor/autoload.php');

session_start();

$app = new \Slim\Slim();

// simple auth
if(empty($_SESSION['dbauth'])) {
    header('HTTP/1.1 401 Not logged in');
    exit('Not logged in');
};

$db = new \Bravado\DB($_SESSION['dbauth']['uri'], $_SESSION['dbauth']['username'], $_SESSION['dbauth']['password']);

$app->get('/', function() use ($db) {


	echo json_encode($db->query('SHOW DATABASES'));
});

$app->get('/:database/:table/:id', function($database, $table, $id) use ($app, $db) {
    $response = $app->response();

    // TODO parametros via GET
    $db->pdo->exec("USE $database");
    $response['Content-Type'] = 'application/json';
    $result = $db->query("SELECT * FROM $table WHERE id = $id");
    if(count($result) > 0) {
        $response->body(json_encode($result[0]));
    }
    else {
        exit('no results found');
    }

});

$app->get('/:database/:table', function($database, $table) use ($app, $db) {
    $response = $app->response();

    // TODO parametros via GET
    $db->pdo->exec("USE $database");
    $response['Content-Type'] = 'application/json';
    $response->body(json_encode($db->query("SELECT * FROM $table")));
});

$app->options('/:database/:table', function($database, $table) use ($app, $db) {
    $response = $app->response();

    $db->pdo->exec("USE $database");
    $response['Content-Type'] = 'application/json';
    $response->body(json_encode($db->query("DESCRIBE $table")));
});

$app->post('/:database/:table', function($database, $table) use ($app, $db) {
    $db->pdo->exec("USE $database");
    $response['Content-Type'] = 'application/json';
    $response->body('');
    throw new Exception("INSERT INTO $table ...");
});

$app->get('/:database', function($database) use ($db) {
    $db->pdo->exec("USE $database");
    $response['Content-Type'] = 'application/json';
    $response->body(json_encode($db->query('SHOW TABLES')));
});





$app->run();
