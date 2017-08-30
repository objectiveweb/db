<?php
/**
 * Tests for the DB\Table
 */
include dirname(__DIR__) . '/vendor/autoload.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Table;

class CrudTest extends PHPUnit_Framework_TestCase
{
    /** @var  Table */
    static protected $table;
    static protected $testData = [
        1 => ['name' => 'test'],
        2 => ['name' => 'test1'],
        3 => ['name' => 'test2'],
        4 => ['name' => 'test3'],
        5 => ['name' => null]
    ];

    public static function setUpBeforeClass()
    {
        $db = DB::connect('mysql:dbname=objectiveweb;host=127.0.0.1', 'root', getenv('MYSQL_PASSWORD'));
        $db->query('drop table if exists db_test')->exec();

        $db->query('create table db_test
            (`id` INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255));')->exec();

        self::$table = $db->table('db_test');

    }

    public function testInsert()
    {
        foreach (self::$testData as $k => $v) {
            $id = self::$table->post($v);
            $this->assertEquals($k, $id['id']);
        }
    }

    /**
     * @depends testInsert
     */
    public function testIndex()
    {
        $rows = self::$table->index();

        $this->assertEquals(5, count($rows));
        $this->assertEquals('test', $rows[0]['name']);

        $rows = self::$table->get();

        $this->assertEquals(5, count($rows));
        $this->assertEquals('test', $rows[0]['name']);

        $count = 0;
        foreach ($rows as $key => $value) {
            $this->assertEquals(self::$testData[$value['id']]['name'], $value['name']);
            $count++;
        }

        $this->assertEquals(5, $count);
    }

    /**
     * @depends testInsert
     */
    public function testPagination()
    {
        $data = self::$table->index(array('range' => [0, 1], 'sort' => ['id', 'asc']));

        $this->assertEquals(2, count($data));
        $this->assertEquals('test', $data[0]['name']);
        $this->assertEquals('test1', $data[1]['name']);
        $this->assertEquals(5, $data->total());

        $data = self::$table->index(array('range' => [2, 3], 'sort' => ['id', 'asc']));

        $this->assertEquals(2, count($data));
        $this->assertEquals('test2', $data[0]['name']);
        $this->assertEquals('test3', $data[1]['name']);
        $this->assertEquals(5, $data->total());

        $data = self::$table->index(array('range' => [4, 5], 'sort' => ['id', 'asc']));

        $this->assertEquals(1, count($data));
        $this->assertEquals(null, $data[0]['name']);
        $this->assertEquals(5, $data->total());
    }

    /**
     * @depends testPagination
     */
    public function testUpdate()
    {
        $r = self::$table->put(array('name' => 'test1'), array('name' => 'test4'));

        $this->assertEquals(1, $r['updated']);
    }

    /**
     * @depends testUpdate
     */
    public function testGetCollection()
    {
        $r = self::$table->get(['filter' => ['name' => 'test4']]);
        $this->assertNotEmpty($r);

        $this->assertEquals('2', $r[0]['id']);
        $this->assertEquals('test4', $r[0]['name']);
    }

    /**
     * @depends testUpdate
     */
    public function testGetParams()
    {
        $r = self::$table->get(1, array('fields' => 'name'));

        $this->assertNotEmpty($r);

        $this->assertEquals(1, count($r));
        $this->assertEquals('test', $r['name']);
    }

    /**
     * @depends testUpdate
     */
    public function testUpdateKey()
    {
        $r = self::$table->put(3, array('name' => 'test2.1'));

        $this->assertEquals(1, $r['updated']);
    }

    /**
     * @depends testUpdateKey
     */
    public function testSelectKey()
    {
        $r = self::$table->get(3);

        $this->assertEquals('test2.1', $r['name']);
    }

    public function testDelete()
    {
        $data = self::$table->index();
        $this->assertEquals($data->total(), 5);

        $r = self::$table->delete(array('name' => 'test1'));
        $this->assertEquals(0, $r);

        $r = self::$table->delete(array('name' => 'test4'));
        $this->assertEquals(1, $r);

        $data = self::$table->index();
        $this->assertEquals($data->total(), 4);
    }
}
