<?php

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use PHPUnit\Framework\TestCase;

class DBTest extends TestCase
{
    protected static DB $db;

    public static function setUpBeforeClass(): void
    {
        static::$db = DbTestBootstrap::connect();
        DbTestBootstrap::createDbTestTable(static::$db, false);
    }

    public function testInsert(): void
    {
        $r = self::$db->insert('db_test', ['name' => 'test']);
        $this->assertEquals(1, (int) $r);

        $r = self::$db->insert('db_test', ['name' => 'test1']);
        $this->assertEquals(2, (int) $r);

        $r = self::$db->insert('db_test', ['name' => 'test2']);
        $this->assertEquals(3, (int) $r);

        $r = self::$db->insert('db_test', ['name' => 'test3']);
        $this->assertEquals(4, (int) $r);

        $r = self::$db->insert('db_test', ['name' => null]);
        $this->assertEquals(5, (int) $r);
    }

    /** @depends testInsert */
    public function testSelectAll(): void
    {
        $rows = self::$db->select('db_test')->all();

        $this->assertEquals(5, count($rows));
        $this->assertEquals('test', $rows[0]['name']);
    }

    /** @depends testInsert */
    public function testSelectParams(): void
    {
        $r = self::$db->select('db_test', ['name' => 'test3'], ['fields' => ['id']])->all();

        $this->assertNotEmpty($r);
        $keys = array_keys($r[0]);
        $this->assertEquals(1, count($keys));
        $this->assertEquals('id', $keys[0]);
        $this->assertEquals(4, (int) $r[0]['id']);
    }

    /** @depends testInsert */
    public function testSelectLike(): void
    {
        $rows = self::$db->select('db_test', ['name' => 'test%'])->all();

        $this->assertEquals(4, count($rows));
        $this->assertEquals('test', $rows[0]['name']);
    }

    /** @depends testInsert */
    public function testSelectMap(): void
    {
        $map = self::$db->select('db_test')->map('id');

        $this->assertEquals(5, count($map));
        $this->assertEquals('test', $map[1]['name']);
        $this->assertEquals('test1', $map[2]['name']);
        $this->assertEquals('test2', $map[3]['name']);
        $this->assertEquals('test3', $map[4]['name']);
        $this->assertEquals(null, $map[5]['name']);
    }

    /** @depends testInsert */
    public function testSelectIn(): void
    {
        $rows = self::$db->select('db_test', ['id' => [2, 3, 4]])->all();

        $this->assertEquals(3, count($rows));
        $this->assertEquals(2, (int) $rows[0]['id']);
        $this->assertEquals(3, (int) $rows[1]['id']);
        $this->assertEquals(4, (int) $rows[2]['id']);
    }

    /** @depends testInsert */
    public function testUpdate(): void
    {
        $r = self::$db->update('db_test', ['name' => 'test4'], ['name' => 'test1']);

        $this->assertEquals(1, $r);
    }

    /** @depends testUpdate */
    public function testSelectFetch(): void
    {
        $r = self::$db->select('db_test', ['name' => 'test4'])->fetch();

        $this->assertEquals('test4', $r['name']);
    }

    /** @depends testUpdate */
    public function testSelectEmptyResults(): void
    {
        $r = self::$db->select('db_test', ['name' => 'test5'])->all();

        $this->assertEmpty(count($r));
    }

    /** @depends testUpdate */
    public function testSelectNull(): void
    {
        $r = self::$db->select('db_test', ['name' => null])->all();

        $this->assertEquals(1, count($r));
        $this->assertEquals(5, (int) $r[0]['id']);
    }

    /** @depends testUpdate */
    public function testDelete(): void
    {
        $rows = self::$db->select('db_test')->all();
        $this->assertEquals(count($rows), 5);

        $r = self::$db->delete('db_test', ['name' => 'test1']);
        $this->assertEquals(0, $r);

        $r = self::$db->delete('db_test', ['name' => 'test4']);
        $this->assertEquals(1, $r);

        $rows = self::$db->select('db_test')->all();
        $this->assertEquals(count($rows), 4);
    }
}
