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
    protected $db;

    protected function setUp() {
        $this->db = new DB('mysql:dbname=objectiveweb;host=192.168.56.101', 'root');
    }

    public function testSetup() {

        $this->db->query('drop table if exists db_test');

        $this->assertNotFalse($this->db->exec());

        $this->db->query('create table db_test
            (`id` INT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255));');

        $this->assertNotFalse($this->db->exec());

    }

    public function testInsert() {
        $r = $this->db->insert('db_test', [ 'name' => 'test']);

        $this->assertNotFalse($r);
    }

    public function testSelectAll() {
        $rows = $this->db->select('db_test')->all();

        $this->assertEquals(1, count($rows));
        $this->assertEquals('test', $rows[0]['name']);
    }

    public function testUpdate() {
        $r = $this->db->update('db_test', ['name' => 'test1'], [ 'name' => 'test']);

        $this->assertNotFalse($r);
    }


    public function testSelectFetch() {
        $r = $this->db->select('db_test', [ 'name' => 'test1' ])->fetch();

        $this->assertEquals('test1', $r['name']);

    }

    public function testDelete() {
        $r = $this->db->destroy('db_test', ['name' => 'test1']);

        $this->assertEquals(1, $r);
    }
}