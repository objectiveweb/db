<?php

declare(strict_types=1);

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Table;
use PHPUnit\Framework\TestCase;

class TableInheritanceTest extends TestCase
{
    private DB $db;
    private Table $table;

    protected function setUp(): void
    {
        $this->db = DbTestBootstrap::connect();
        DbTestBootstrap::createInheritanceTables($this->db);

        $this->table = $this->db->table('things_ext', [
            'extends' => [
                'table' => 'things_base',
                'fields' => ['common_name', 'common_kind'],
                'key' => 'id',
            ],
        ]);
    }

    public function testInsertSplitsPayloadAcrossBaseAndMainTables(): void
    {
        $inserted = $this->table->insert([
            'common_name' => 'base-1',
            'common_kind' => 'kind-a',
            'extra_value' => 'extra-1',
        ]);

        $this->assertNotNull($inserted);
        $id = (int) $inserted['id'];

        $base = $this->db->select('things_base', ['id' => $id])->fetch();
        $main = $this->db->select('things_ext', ['id' => $id])->fetch();

        $this->assertNotFalse($base);
        $this->assertNotFalse($main);
        $this->assertSame('base-1', $base['common_name']);
        $this->assertSame('kind-a', $base['common_kind']);
        $this->assertSame('extra-1', $main['extra_value']);
    }

    public function testSelectIncludesBaseJoinByDefault(): void
    {
        $inserted = $this->table->insert([
            'common_name' => 'base-join',
            'common_kind' => 'kind-join',
            'extra_value' => 'extra-join',
        ]);

        $id = (int) $inserted['id'];

        $row = $this->table->get($id, [
            'fields' => [
                'things_ext.id',
                'things_base.common_name',
                'things_ext.extra_value',
            ],
        ]);

        $this->assertSame('base-join', $row['common_name']);
        $this->assertSame('extra-join', $row['extra_value']);
    }

    public function testUpdateSplitsPayloadAcrossBaseAndMainTables(): void
    {
        $id = (int) $this->table->insert([
            'common_name' => 'before',
            'common_kind' => 'kind-before',
            'extra_value' => 'extra-before',
        ])['id'];

        $updated = $this->table->update($id, [
            'common_name' => 'after',
            'extra_value' => 'extra-after',
        ]);

        $this->assertSame(1, $updated['updated']);

        $base = $this->db->select('things_base', ['id' => $id])->fetch();
        $main = $this->db->select('things_ext', ['id' => $id])->fetch();

        $this->assertSame('after', $base['common_name']);
        $this->assertSame('extra-after', $main['extra_value']);
    }

    public function testUpdateByMainTableFilterAlsoUpdatesBaseTable(): void
    {
        $this->table->insert([
            'common_name' => 'batch-1',
            'common_kind' => 'kind-batch',
            'extra_value' => 'batch-target',
        ]);
        $this->table->insert([
            'common_name' => 'batch-2',
            'common_kind' => 'kind-batch',
            'extra_value' => 'batch-target',
        ]);

        $updated = $this->table->update(['extra_value' => 'batch-target'], [
            'common_kind' => 'kind-updated',
        ]);

        $this->assertSame(2, $updated['updated']);

        $rows = $this->db->select('things_base', ['common_kind' => 'kind-updated'])->all();
        $this->assertCount(2, $rows);
    }

    public function testUpdateWithBaseTableFilterUpdatesMainTable(): void
    {
        $this->table->insert([
            'common_name' => 'approved-1',
            'common_kind' => 'approved',
            'extra_value' => 'pending',
        ]);
        $this->table->insert([
            'common_name' => 'pending-1',
            'common_kind' => 'pending',
            'extra_value' => 'pending',
        ]);

        $updated = $this->table->update(['common_kind' => 'approved'], [
            'extra_value' => 'done',
        ]);

        $this->assertSame(1, $updated['updated']);

        $rowsDone = $this->db->select('things_ext', ['extra_value' => 'done'])->all();
        $this->assertCount(1, $rowsDone);
    }

    public function testUpdateWithMixedBaseAndMainFilters(): void
    {
        $this->table->insert([
            'common_name' => 'mix-1',
            'common_kind' => 'approved',
            'extra_value' => 'not-updated',
        ]);
        $this->table->insert([
            'common_name' => 'mix-2',
            'common_kind' => 'approved',
            'extra_value' => 'updated',
        ]);

        $updated = $this->table->update([
            'common_kind' => 'approved',
            'extra_value' => 'not-updated',
        ], [
            'extra_value' => 'updated',
        ]);

        $this->assertSame(1, $updated['updated']);

        $rows = $this->db->select('things_ext', ['extra_value' => 'updated'])->all();
        $this->assertCount(2, $rows);
    }

    public function testUpdateBaseFieldWhereMainFieldMatches(): void
    {
        $this->table->insert([
            'common_name' => 'base-from-main-1',
            'common_kind' => 'draft',
            'extra_value' => 'x',
        ]);
        $this->table->insert([
            'common_name' => 'base-from-main-2',
            'common_kind' => 'draft',
            'extra_value' => 'y',
        ]);

        $updated = $this->table->update(['extra_value' => 'x'], [
            'common_kind' => 'approved',
        ]);

        $this->assertSame(1, $updated['updated']);

        $approved = $this->db->select('things_base', ['common_kind' => 'approved'])->all();
        $this->assertCount(1, $approved);
        $this->assertSame('base-from-main-1', $approved[0]['common_name']);
    }

    public function testUpdateMainFieldWhereBaseFieldMatches(): void
    {
        $this->table->insert([
            'common_name' => 'main-from-base-1',
            'common_kind' => 'approved',
            'extra_value' => 'pending',
        ]);
        $this->table->insert([
            'common_name' => 'main-from-base-2',
            'common_kind' => 'draft',
            'extra_value' => 'pending',
        ]);

        $updated = $this->table->update(['common_kind' => 'approved'], [
            'extra_value' => 'done',
        ]);

        $this->assertSame(1, $updated['updated']);

        $done = $this->db->select('things_ext', ['extra_value' => 'done'])->all();
        $this->assertCount(1, $done);
    }

    public function testCustomExtendsKeyIsUsedForJoinAndUpdates(): void
    {
        DbTestBootstrap::createInheritanceTablesWithCustomBaseKey($this->db);
        $table = $this->db->table('things_ext_key', [
            'extends' => [
                'table' => 'things_base_key',
                'fields' => ['common_kind'],
                'key' => 'ext_id',
            ],
        ]);

        $inserted = $table->insert([
            'common_kind' => 'approved',
            'extra_value' => 'pending',
        ]);
        $id = (int) $inserted['id'];

        $base = $this->db->select('things_base_key', ['ext_id' => $id])->fetch();
        $this->assertNotFalse($base);
        $this->assertSame('approved', $base['common_kind']);

        $updated = $table->update(['common_kind' => 'approved'], [
            'extra_value' => 'done',
        ]);
        $this->assertSame(1, $updated['updated']);

        $main = $this->db->select('things_ext_key', ['id' => $id])->fetch();
        $this->assertNotFalse($main);
        $this->assertSame('done', $main['extra_value']);
    }
}
