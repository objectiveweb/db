<?php
/**
 * Created by IntelliJ IDEA.
 * User: guigouz
 * Date: 5/31/13
 * Time: 11:58 AM
 * To change this template use File | Settings | File Templates.
 */

require('vendor/autoload.php');
session_start();

$app = new \Slim\Slim();

$app->get('/', function() {
    echo <<< EOF
    <form action="" method="post">
        <input type="text" name="uri" value="mysql:host=localhost;dbname=objectiveweb;charset=utf8"/><br/>
        <input type="text" name="username" value="root"/><br/>
        <input type="password" name="password" value="root"/>
        <input type="submit" value="Login"/>
    </form>
EOF;
    exit;
});

$app->get('/logout', function() use ($app) {
    unset($_SESSION['dbauth']);
    $app->redirect('/');
});

$app->post('/', function() use ($app) {
    $_SESSION['dbauth'] = $_POST;

    $app->redirect('api.php');
});


$app->run();