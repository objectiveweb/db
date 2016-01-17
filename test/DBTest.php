<?php
/**
 * Created by IntelliJ IDEA.
 * User: guigouz
 * Date: 16/02/15
 * Time: 15:43
 */
include dirname(__DIR__) . '/vendor/autoload.php';

use Objectiveweb\DB;

/**
 * @requires PHP 5.3
 */
class DBTest extends PHPUnit_Framework_TestCase
{

    /** @var  DB */
    static protected $db;

    public static function setUpBeforeClass()
    {
        self::$db = DB::connect('mysql:dbname=objectiveweb;host=127.0.0.1', 'root', getenv('MYSQL_PASSWORD'));
        self::$db->query('drop table if exists db_test')->exec();

        self::$db->query('create table db_test
            (`id` INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255));')->exec();
    }

    public function testInsert()
    {
        $r = self::$db->insert('db_test', array('name' => 'test'));

        $this->assertEquals(1, $r);

        $r = self::$db->insert('db_test', array('name' => 'test1'));

        $this->assertEquals(2, $r);

        $r = self::$db->insert('db_test', array('name' => 'test2'));

        $this->assertEquals(3, $r);

        $r = self::$db->insert('db_test', array('name' => 'test3'));

        $this->assertEquals(4, $r);

        $r = self::$db->insert('db_test', array('name' => null));

        $this->assertEquals(5, $r);


    }

    public function testSelectAll()
    {
        $rows = self::$db->select('db_test')->all();

        $this->assertEquals(5, count($rows));
        $this->assertEquals('test', $rows[0]['name']);
    }

	public function testSelectLike() {
		$rows = self::$db->select('db_test', array('name' => 'test%'))->all();
		
		$this->assertEquals(4, count($rows));
        $this->assertEquals('test', $rows[0]['name']);
	}
    public function testSelectMap() {
        $map = self::$db->select('db_test')->map('id');

        $this->assertEquals(5, count($map));
        $this->assertEquals('test', $map[1]['name']);
        $this->assertEquals('test1', $map[2]['name']);
        $this->assertEquals('test2', $map[3]['name']);
        $this->assertEquals('test3', $map[4]['name']);
        $this->assertEquals(null, $map[5]['name']);
    }

    public function testSelectIn() {
        $rows = self::$db->select('db_test', array( 'id' => array( 2, 3, 4 ) ))->all();

        $this->assertEquals(3, count($rows));
        $this->assertEquals(2, $rows[0]['id']);
        $this->assertEquals(3, $rows[1]['id']);
        $this->assertEquals(4, $rows[2]['id']);
    }

    public function testUpdate()
    {
        $r = self::$db->update('db_test', array('name' => 'test4'), array('name' => 'test1'));

        $this->assertEquals(1, $r);
    }


    public function testSelectFetch()
    {
        $r = self::$db->select('db_test', array('name' => 'test4'))->fetch();

        $this->assertEquals('test4', $r['name']);

    }

    public function testSelectEmptyResults()
    {
        $r = self::$db->select('db_test', array('name' => 'test5'))->all();

        $this->assertEmpty(count($r));

    }

    public function testSelectNull()
    {
        $r = self::$db->select('db_test', array('name' => null))->all();

        $this->assertEquals(1, count($r));

        $this->assertEquals(5, $r[0]['id']);
    }

    public function testDelete()
    {
        $rows = self::$db->select('db_test')->all();
        $this->assertEquals(count($rows), 5);

        $r = self::$db->delete('db_test', array('name' => 'test1'));
        $this->assertEquals(0, $r);

        $r = self::$db->delete('db_test', array('name' => 'test4'));
        $this->assertEquals(1, $r);

        $rows = self::$db->select('db_test')->all();
        $this->assertEquals(count($rows), 4);

    }
}