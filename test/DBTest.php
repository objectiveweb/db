<?php
/**
 * Created by IntelliJ IDEA.
 * User: guigouz
 * Date: 16/02/15
 * Time: 15:43
 */
include dirname(__DIR__).'/vendor/autoload.php';

use Objectiveweb\DB;

class DBTest extends PHPUnit_Framework_TestCase {

    /** @var  DB */
    static protected $db;

    public static function setUpBeforeClass() {
        self::$db = new DB('mysql:dbname=objectiveweb;host=192.168.56.101', 'root');
        self::$db->query('drop table if exists db_test')->exec();

        self::$db->query('create table db_test
            (`id` INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255));')->exec();
    }

    public function testInsert() {
        $r = self::$db->insert('db_test', [ 'name' => 'test']);

        $this->assertEquals(1, $r);

        $r = self::$db->insert('db_test', [ 'name' => 'test1']);

        $this->assertEquals(2, $r);

        $r = self::$db->insert('db_test', [ 'name' => 'test2']);

        $this->assertEquals(3, $r);

        $r = self::$db->insert('db_test', [ 'name' => 'test3']);

        $this->assertEquals(4, $r);
    }

    public function testSelectAll() {
        $rows = self::$db->select('db_test')->all();

        $this->assertEquals(4, count($rows));
        $this->assertEquals('test', $rows[0]['name']);
    }

    public function testUpdate() {
        $r = self::$db->update('db_test', ['name' => 'test4'], [ 'name' => 'test1']);

        $this->assertEquals(1, $r);
    }


    public function testSelectFetch() {
        $r = self::$db->select('db_test',[ 'name' => 'test4' ])->fetch();

        $this->assertEquals('test4', $r['name']);

    }

    public function testSelectEmptyResults() {
        $r = self::$db->select('db_test',[ 'name' => 'test5' ])->all();

        $this->assertEmpty(count($r));

    }

    // TODO test select with null WHERE parameter
    public function testDelete() {
        $rows = self::$db->select('db_test')->all();
        $this->assertEquals(count($rows), 4);

        $r = self::$db->destroy('db_test', ['name' => 'test1']);
        $this->assertEquals(0, $r);

        $r = self::$db->destroy('db_test', ['name' => 'test4']);
        $this->assertEquals(1, $r);

        $rows = self::$db->select('db_test')->all();
        $this->assertEquals(count($rows), 3);

    }
}