<?php

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Exception\InvalidQueryException;
use Objectiveweb\DB\Exception\NotFoundException;
use Objectiveweb\DB\Table;
use PHPUnit\Framework\TestCase;

class QueryAndTableCoverageTest extends TestCase
{
    private DB $db;
    private Table $table;

    protected function setUp(): void
    {
        $this->db = DbTestBootstrap::connect();
        DbTestBootstrap::createThings($this->db);

        $this->table = $this->db->table('things');
        $this->table->insert(['name' => 'x1', 'kind' => 'x']);
        $this->table->insert(['name' => 'x2', 'kind' => 'x']);
        $this->table->insert(['name' => 'x2', 'kind' => 'y']);
        $this->table->insert(['name' => 'y1', 'kind' => 'y']);
    }

    public function testQueryBindFetchAllAndExecUpdate(): void
    {
        $query = $this->db->query('SELECT * FROM things WHERE id = :id');
        $query->bind('id', 1)->exec();

        $first = $query->fetch();
        $this->assertSame('x1', $first['name']);

        $this->assertCount(0, $query->all());

        $updated = $this->db->query('UPDATE things SET name = :name WHERE id = :id')->exec([
            'name' => 'x1-updated',
            'id' => 1,
        ]);

        $this->assertSame(1, $updated);
        $this->assertSame('x1-updated', $this->table->get(1)['name']);
    }

    public function testQueryMapInvalidFieldThrows(): void
    {
        $query = $this->db->select('things');

        $this->expectException(InvalidQueryException::class);
        $query->map('missing_field');
    }

    public function testTableInsertFindByDeleteScalarAndNotFound(): void
    {
        $inserted = $this->table->insert(['name' => 'z1', 'kind' => 'z']);
        $this->assertArrayHasKey('id', $inserted);

        $found = $this->table->findBy('kind', 'x');
        $this->assertSame(2, count($found));

        $deleted = $this->table->delete(2);
        $this->assertSame(1, $deleted);

        $this->expectException(NotFoundException::class);
        $this->table->get(9999);
    }

    public function testTableGetWithJsonFilterAndRange(): void
    {
        $data = $this->table->select([], [
            'filter' => '{"kind":"x"}',
            'range' => '[0,0]',
            'sort' => ['id', 'asc'],
        ]);

        $this->assertSame(1, count($data));
        $this->assertSame(2, $data->total());
    }

    public function testSelectParamsFilterMergesWithFirstFilterArgument(): void
    {
        $data = $this->table->select(
            ['name' => 'x2'],
            ['filter' => ['kind' => 'x']]
        );

        $this->assertSame(1, count($data));
        $this->assertSame('x2', $data[0]['name']);
        $this->assertSame('x', $data[0]['kind']);
    }

    public function testOrderSupportsIsNullExpression(): void
    {
        $this->table->insert(['name' => null, 'kind' => 'z']);

        $rows = $this->db->select('things', null, [
            'order' => ['name is null desc', 'id asc'],
        ])->all();

        $this->assertNull($rows[0]['name']);
    }
}
