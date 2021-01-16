<?php
/**
 * Tests for the DB\Table
 */
include dirname(__DIR__) . '/vendor/autoload.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Table;


class DbTestTable extends DB\Table
{
    protected $table = 'db_test';
}

class TableFromClassTest extends CrudTest
{
    /** @var  Table */
    static protected $table;

    public static function setUpBeforeClass()
    {
        $db = DB::connect('mysql:dbname=objectiveweb;host=127.0.0.1', 'root', getenv('MYSQL_PASSWORD'));
        $db->query('drop table if exists db_test')->exec();

        $db->query('create table db_test
            (`id` INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255), `f1` VARCHAR(255), `f2` VARCHAR(255), `f3` VARCHAR(255));')->exec();

        self::$table = $db->table('DbTestTable');
    }

    public function testClass()
    {
        $this->assertInstanceOf(DbTestTable::class, self::$table);
    }
}
