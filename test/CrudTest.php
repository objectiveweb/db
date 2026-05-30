<?php

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Table;
use PHPUnit\Framework\TestCase;

class CrudTest extends TestCase
{
    protected static Table $table;

    /** @var array<int,array<string,mixed>> */
    protected static array $testData = [
        1 => ['name' => 'test', 'f1' => 'test', 'f2' => 'test', 'f3' => 'test'],
        2 => ['name' => 'test1', 'f1' => 'test1', 'f2' => 'test1', 'f3' => 'test1'],
        3 => ['name' => 'test2', 'f1' => 'test2', 'f2' => 'test2', 'f3' => 'test2'],
        4 => ['name' => 'test3', 'f1' => 'test3', 'f2' => 'test3', 'f3' => 'test3'],
        5 => ['name' => null, 'f1' => 'test3', 'f2' => 'test3', 'f3' => 'test3'],
    ];

    public static function setUpBeforeClass(): void
    {
        $db = DbTestBootstrap::connect();
        DbTestBootstrap::createDbTestTable($db, true);
        static::$table = $db->table('db_test');
    }

    public function testInsert(): void
    {
        foreach (self::$testData as $k => $v) {
            $id = static::$table->insert($v);
            $this->assertEquals($k, (int) $id['id']);
        }
    }

    /** @depends testInsert */
    public function testIndex(): void
    {
        $rows = static::$table->select();

        $this->assertEquals(5, count($rows));
        $this->assertEquals('test', $rows[0]['name']);

        $rows = static::$table->get();

        $this->assertEquals(5, count($rows));
        $this->assertEquals('test', $rows[0]['name']);

        $count = 0;
        foreach ($rows as $value) {
            $this->assertEquals(self::$testData[$value['id']]['name'], $value['name']);
            $count++;
        }

        $this->assertEquals(5, $count);
    }

    /** @depends testInsert */
    public function testCollectionSupportsByReferenceIteration(): void
    {
        $rows = static::$table->select([], ['sort' => ['id', 'asc']]);

        foreach ($rows as &$row) {
            $row['name'] = 'mutated-' . ((string) $row['id']);
        }
        unset($row);

        $this->assertSame('mutated-1', $rows[0]['name']);
        $this->assertSame('mutated-2', $rows[1]['name']);
    }

    /** @depends testInsert */
    public function testFields(): void
    {
        $data = static::$table->select([], ['fields' => ['id', 'f1', 'f2']]);

        foreach ($data as $result) {
            $this->assertCount(3, $result);
            $this->assertEquals($result['f1'], self::$testData[$result['id']]['f1']);
            $this->assertEquals($result['f2'], self::$testData[$result['id']]['f2']);
        }
    }

    /** @depends testInsert */
    public function testPagination(): void
    {
        $data = static::$table->select([], ['range' => [0, 1], 'sort' => ['id', 'asc']]);

        $this->assertEquals(2, count($data));
        $this->assertEquals('test', $data[0]['name']);
        $this->assertEquals('test1', $data[1]['name']);
        $this->assertEquals(5, $data->total());

        $data = static::$table->select([], ['range' => [2, 3], 'sort' => ['id', 'asc']]);

        $this->assertEquals(2, count($data));
        $this->assertEquals('test2', $data[0]['name']);
        $this->assertEquals('test3', $data[1]['name']);
        $this->assertEquals(5, $data->total());

        $data = static::$table->select([], ['range' => [4, 5], 'sort' => ['id', 'asc']]);

        $this->assertEquals(1, count($data));
        $this->assertEquals(null, $data[0]['name']);
        $this->assertEquals(5, $data->total());
    }

    /** @depends testInsert */
    public function testMultipleSortFields(): void
    {
        $data = static::$table->select([], [
            'sort' => [
                ['f3', 'desc'],
                ['id', 'asc'],
            ],
        ]);

        $this->assertEquals(5, count($data));
        $this->assertEquals(4, (int) $data[0]['id']);
        $this->assertEquals(5, (int) $data[1]['id']);
    }

    /** @depends testPagination */
    public function testUpdate(): void
    {
        $r = static::$table->update(['name' => 'test1'], ['name' => 'test4']);

        $this->assertEquals(1, $r['updated']);
    }

    /** @depends testUpdate */
    public function testGetCollection(): void
    {
        $r = static::$table->get(['name' => 'test4']);
        $this->assertNotEmpty($r);

        $this->assertEquals('2', $r[0]['id']);
        $this->assertEquals('test4', $r[0]['name']);
    }

    /** @depends testUpdate */
    public function testGetParams(): void
    {
        $r = static::$table->get(1, ['fields' => 'name']);

        $this->assertNotEmpty($r);

        $this->assertEquals(1, count($r));
        $this->assertEquals('test', $r['name']);
    }

    /** @depends testUpdate */
    public function testUpdateKey(): void
    {
        $r = static::$table->update(3, ['name' => 'test2.1']);

        $this->assertEquals(1, $r['updated']);
    }

    /** @depends testUpdateKey */
    public function testSelectKey(): void
    {
        $r = static::$table->get(3);

        $this->assertEquals('test2.1', $r['name']);
    }

    /** @depends testSelectKey */
    public function testDelete(): void
    {
        $data = static::$table->select();
        $this->assertEquals($data->total(), 5);

        $r = static::$table->delete(['name' => 'test1']);
        $this->assertEquals(0, $r);

        $r = static::$table->delete(['name' => 'test4']);
        $this->assertEquals(1, $r);

        $data = static::$table->select();
        $this->assertEquals($data->total(), 4);
    }
}
