<?php

declare(strict_types=1);

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Exception\InvalidQueryException;
use PHPUnit\Framework\TestCase;

class SQLBehaviorCoverageTest extends TestCase
{
    private DB $db;

    protected function setUp(): void
    {
        $this->db = DbTestBootstrap::connect();

        $this->db->query('DROP TABLE IF EXISTS item_meta')->exec();
        $this->db->query('DROP TABLE IF EXISTS items')->exec();
        $this->db->query('DROP TABLE IF EXISTS person_links')->exec();
        $this->db->query('DROP TABLE IF EXISTS people')->exec();

        $this->db->query(sprintf(
            'CREATE TABLE items (%s, name VARCHAR(255), kind VARCHAR(50), score INTEGER, deleted_at VARCHAR(25))',
            $this->idDefinition()
        ))->exec();

        $this->db->query(sprintf(
            'CREATE TABLE item_meta (%s, item_id INTEGER, tag VARCHAR(50))',
            $this->idDefinition()
        ))->exec();
        $this->db->query(sprintf(
            'CREATE TABLE people (%s, name VARCHAR(255))',
            $this->idDefinition()
        ))->exec();
        $this->db->query(sprintf(
            'CREATE TABLE person_links (%s, child_id INTEGER, parent_a_id INTEGER, parent_b_id INTEGER)',
            $this->idDefinition()
        ))->exec();

        $this->db->insert('items', ['name' => 'alpha', 'kind' => 'x', 'score' => 10, 'deleted_at' => null]);
        $this->db->insert('items', ['name' => 'beta', 'kind' => 'x', 'score' => 20, 'deleted_at' => '2025-01-01']);
        $this->db->insert('items', ['name' => 'gamma', 'kind' => 'y', 'score' => 5, 'deleted_at' => null]);

        $this->db->insert('item_meta', ['item_id' => 1, 'tag' => 't1']);
        $this->db->insert('item_meta', ['item_id' => 2, 'tag' => 't2']);

        $this->db->insert('people', ['name' => 'child']);
        $this->db->insert('people', ['name' => 'parent_a']);
        $this->db->insert('people', ['name' => 'parent_b']);
        $this->db->insert('person_links', ['child_id' => 1, 'parent_a_id' => 2, 'parent_b_id' => 3]);
    }

    public function testWhereOperatorBranches(): void
    {
        $notEqual = $this->db->select('items', ['!name' => 'alpha'])->all();
        $this->assertCount(2, $notEqual);

        $notLike = $this->db->select('items', ['!name' => 'a%'])->all();
        $this->assertCount(2, $notLike);

        $isNull = $this->db->select('items', ['deleted_at' => null])->all();
        $this->assertCount(2, $isNull);

        $isNotNull = $this->db->select('items', ['!deleted_at' => null])->all();
        $this->assertCount(1, $isNotNull);

        $emptyIn = $this->db->select('items', ['id' => []])->all();
        $this->assertCount(0, $emptyIn);

        $emptyNotIn = $this->db->select('items', ['!id' => []])->all();
        $this->assertCount(3, $emptyNotIn);
    }

    public function testFieldAggregationGroupAndOrderModes(): void
    {
        $grouped = $this->db->select('items', null, [
            'fields' => ['kind', 'total' => 'COUNT(*)', 'max_score' => 'MAX(score)'],
            'group' => 'kind',
            'order' => 'kind ASC',
        ])->all();

        $this->assertCount(2, $grouped);
        $this->assertSame('x', $grouped[0]['kind']);
        $this->assertSame('2', (string) $grouped[0]['total']);

        $onlyTableWildcard = $this->db->select('items', ['id' => 1], [
            'fields' => ['items.*'],
        ])->fetch();

        $this->assertSame('alpha', $onlyTableWildcard['name']);
    }

    public function testSelectSupportsCountAndAvgFunctionFields(): void
    {
        $row = $this->db->select('items', ['kind' => 'x'], [
            'fields' => [
                'total' => 'COUNT(*)',
                'avg_score' => 'AVG(score)',
            ],
        ])->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('2', (string) $row['total']);
        $this->assertEqualsWithDelta(15.0, (float) $row['avg_score'], 0.00001);
    }

    public function testJoinVariantsAndOrderList(): void
    {
        $rows = $this->db->select('items', null, [
            'fields' => ['items.id', 'items.name', 'm.tag'],
            'join' => ['left:item_meta m' => 'm.item_id = items.id'],
            'order' => 'items.id DESC, items.name ASC',
        ])->all();

        $this->assertCount(3, $rows);
        $this->assertSame('gamma', $rows[0]['name']);
        $this->assertNull($rows[0]['tag']);
    }

    public function testJoinSameTableTwiceWithDifferentAliases(): void
    {
        $rows = $this->db->select('person_links', ['person_links.child_id' => 1], [
            'fields' => [
                'person_links.child_id',
                'parent_a_name' => 'p1.name',
                'parent_b_name' => 'p2.name',
            ],
            'join' => [
                'inner:people p1' => 'p1.id = person_links.parent_a_id',
                'inner:people p2' => 'p2.id = person_links.parent_b_id',
            ],
        ])->all();

        $this->assertCount(1, $rows);
        $this->assertSame('parent_a', $rows[0]['parent_a_name']);
        $this->assertSame('parent_b', $rows[0]['parent_b_name']);
    }

    public function testCrossJoinWithStructuredFormat(): void
    {
        $rows = $this->db->select('items', ['items.id' => 1], [
            'fields' => ['item_name' => 'items.name', 'person_name' => 'p.name'],
            'join' => [
                ['type' => 'cross', 'table' => 'people', 'alias' => 'p'],
            ],
        ])->all();

        $this->assertCount(3, $rows);
        $this->assertSame('alpha', $rows[0]['item_name']);
    }

    public function testRightJoinSupport(): void
    {
        if ($this->driver() === 'sqlite') {
            $this->markTestSkipped('RIGHT JOIN is not supported by SQLite.');
        }

        $rows = $this->db->select('people', ['people.name' => 'parent_a'], [
            'fields' => [
                'parent_name' => 'people.name',
                'child_name' => 'p2.name',
            ],
            'join' => [
                ['type' => 'right', 'table' => 'person_links', 'alias' => 'l', 'on' => 'l.parent_a_id = people.id'],
                ['type' => 'inner', 'table' => 'people', 'alias' => 'p2', 'on' => 'p2.id = l.child_id'],
            ],
        ])->all();

        $this->assertCount(1, $rows);
        $this->assertSame('parent_a', $rows[0]['parent_name']);
        $this->assertSame('child', $rows[0]['child_name']);
    }

    public function testFullOuterJoinSupport(): void
    {
        if ($this->driver() !== 'pgsql') {
            $this->markTestSkipped('FULL OUTER JOIN test runs on PostgreSQL only.');
        }

        $rows = $this->db->select('people', null, [
            'fields' => ['people.id', 'p2.id'],
            'join' => [
                ['type' => 'full', 'table' => 'people', 'alias' => 'p2', 'on' => 'p2.id = people.id'],
            ],
        ])->all();

        $this->assertCount(3, $rows);
    }

    public function testMutationSafetyAndInvalidSqlInputs(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->db->update('items', ['name' => 'unsafe']);
    }

    public function testDeleteSafetyAndInvalidClauses(): void
    {
        try {
            $this->db->delete('items', null);
            $this->fail('Expected InvalidQueryException for delete without where');
        } catch (InvalidQueryException $e) {
            $this->assertStringContainsString('Unsafe DELETE', $e->getMessage());
        }

        $this->expectException(InvalidQueryException::class);
        $this->db->select('items', null, ['order' => 'name; DROP TABLE items']);
    }

    public function testInvalidGroupAndIdentifierRejection(): void
    {
        try {
            $this->db->select('items', null, ['group' => ['kind']]);
            $this->fail('Expected InvalidQueryException for invalid group');
        } catch (InvalidQueryException $e) {
            $this->assertStringContainsString('group expects a string field', $e->getMessage());
        }

        $this->expectException(InvalidQueryException::class);
        $this->db->select('bad-table')->all();
    }

    private function idDefinition(): string
    {
        return match ((string) (getenv('TEST_DB_DRIVER') ?: 'sqlite')) {
            'mysql' => 'id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL',
            'pgsql' => 'id SERIAL PRIMARY KEY',
            default => 'id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL',
        };
    }

    private function driver(): string
    {
        return (string) (getenv('TEST_DB_DRIVER') ?: 'sqlite');
    }
}
