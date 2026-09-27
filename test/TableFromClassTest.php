<?php

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Table;

class DbTestTable extends DB\Table
{
    protected ?string $table = 'db_test';
}

class TableFromClassTest extends CrudTest
{
    public static function setUpBeforeClass(): void
    {
        $db = DbTestBootstrap::connect();
        DbTestBootstrap::createDbTestTable($db, true);

        static::$table = $db->table(DbTestTable::class);
    }

    public function testClass(): void
    {
        $this->assertInstanceOf(DbTestTable::class, self::$table);
    }
}
