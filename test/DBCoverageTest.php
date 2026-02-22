<?php

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Expr;
use Objectiveweb\DB\Exception\InvalidQueryException;
use Objectiveweb\DB\Exception\TransactionException;
use PHPUnit\Framework\TestCase;

class DBCoverageTest extends TestCase
{
    private DB $db;

    protected function setUp(): void
    {
        $this->db = DbTestBootstrap::connect();
        DbTestBootstrap::createUsersAndProfiles($this->db);

        $this->db->insert('users', ['name' => 'ann', 'group_name' => 'a', 'is_active' => true]);
        $this->db->insert('users', ['name' => 'bob', 'group_name' => 'a', 'is_active' => false]);
        $this->db->insert('users', ['name' => 'cara', 'group_name' => 'b', 'is_active' => true]);

        $this->db->insert('profiles', ['user_id' => 1, 'city' => 'NY']);
        $this->db->insert('profiles', ['user_id' => 2, 'city' => 'SF']);
    }

    public function testTransactionCommitAndRollback(): void
    {
        $id = $this->db->transaction(function (DB $db) {
            return $db->insert('users', ['name' => 'dave', 'group_name' => 'b', 'is_active' => true]);
        });

        $this->assertSame('4', $id);
        $this->assertSame(4, $this->db->count('users'));

        try {
            $this->db->transaction(function (DB $db): void {
                $db->insert('users', ['name' => 'rolled', 'group_name' => 'c', 'is_active' => true]);
                throw new RuntimeException('boom');
            });
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(4, $this->db->count('users'));
    }

    public function testNestedTransactionsCommit(): void
    {
        $this->db->transaction(function (DB $db): void {
            $db->insert('users', ['name' => 'outer-ok', 'group_name' => 'n', 'is_active' => true]);

            $db->transaction(function (DB $db): void {
                $db->insert('users', ['name' => 'inner-ok', 'group_name' => 'n', 'is_active' => true]);
            });
        });

        $this->assertSame(5, $this->db->count('users'));
        $this->assertSame(1, $this->db->count('users', ['name' => 'outer-ok']));
        $this->assertSame(1, $this->db->count('users', ['name' => 'inner-ok']));
    }

    public function testNestedTransactionsInnerRollbackAndOuterCommit(): void
    {
        $this->db->transaction(function (DB $db): void {
            $db->insert('users', ['name' => 'outer-before', 'group_name' => 'n', 'is_active' => true]);

            try {
                $db->transaction(function (DB $db): void {
                    $db->insert('users', ['name' => 'inner-fail', 'group_name' => 'n', 'is_active' => true]);
                    throw new RuntimeException('inner failure');
                });
            } catch (RuntimeException $e) {
                $this->assertSame('inner failure', $e->getMessage());
            }

            $db->insert('users', ['name' => 'outer-after', 'group_name' => 'n', 'is_active' => true]);
        });

        $this->assertSame(5, $this->db->count('users'));
        $this->assertSame(1, $this->db->count('users', ['name' => 'outer-before']));
        $this->assertSame(0, $this->db->count('users', ['name' => 'inner-fail']));
        $this->assertSame(1, $this->db->count('users', ['name' => 'outer-after']));
    }

    public function testNestedTransactionsUncaughtInnerErrorRollsBackOuter(): void
    {
        try {
            $this->db->transaction(function (DB $db): void {
                $db->insert('users', ['name' => 'outer-uncaught', 'group_name' => 'n', 'is_active' => true]);

                $db->transaction(function (DB $db): void {
                    $db->insert('users', ['name' => 'inner-uncaught', 'group_name' => 'n', 'is_active' => true]);
                    throw new RuntimeException('nested uncaught');
                });
            });
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('nested uncaught', $e->getMessage());
        }

        $this->assertSame(3, $this->db->count('users'));
        $this->assertSame(0, $this->db->count('users', ['name' => 'outer-uncaught']));
        $this->assertSame(0, $this->db->count('users', ['name' => 'inner-uncaught']));
    }

    public function testManualTransactionMethods(): void
    {
        $this->assertTrue($this->db->beginTransaction());
        $this->db->insert('users', ['name' => 'temp', 'group_name' => 'z', 'is_active' => true]);
        $this->assertTrue($this->db->rollBack());

        $rows = $this->db->select('users', ['name' => 'temp'])->all();
        $this->assertCount(0, $rows);
    }

    public function testCountGroupJoinOrderAndLimit(): void
    {
        $groupCount = $this->db->count('users', null, ['group' => 'group_name']);
        $this->assertSame(2, $groupCount);

        $rows = $this->db->select('users', ['is_active' => 1], [
            'fields' => ['users.id', 'users.name', 'p.city'],
            'join' => ['profiles p' => 'p.user_id = users.id'],
            'order' => ['users.id', 'desc'],
            'limit' => 1,
            'offset' => 0,
        ])->all();

        $this->assertCount(1, $rows);
        $this->assertSame('ann', $rows[0]['name']);
        $this->assertSame('NY', $rows[0]['city']);
    }

    public function testSelectSupportsExprFieldWithAlias(): void
    {
        $rows = $this->db->select('users', ['name' => 'ann'], [
            'fields' => [
                'users.id',
                'is_ann' => Expr::raw("CASE WHEN users.name = 'ann' THEN 1 ELSE 0 END"),
            ],
        ])->all();

        $this->assertCount(1, $rows);
        $this->assertSame('1', (string)$rows[0]['is_ann']);
    }

    public function testGroupSupportsMultipleFieldsAsStringAndArray(): void
    {
        $countFromString = $this->db->count('users', null, ['group' => 'group_name, is_active']);
        $this->assertSame(3, $countFromString);

        $countFromArray = $this->db->count('users', null, ['group' => ['group_name', 'is_active']]);
        $this->assertSame(3, $countFromArray);
    }

    public function testDebugAndHelpers(): void
    {
        $previousErrorLog = ini_get('error_log');
        ini_set('error_log', '/tmp/objectiveweb-db-test.log');

        $this->db->debug(true);
        $query = $this->db->query('SELECT 1 as ok');
        $this->assertSame('SELECT 1 as ok', $query->debugSql);
        $this->db->debug(false);

        $clean = DB::array_cleanup(['a' => 1, 'b' => 2], ['a'], ['z' => 0]);
        $this->assertSame(['z' => 0, 'a' => 1], $clean);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', DB::now());

        if (is_string($previousErrorLog)) {
            ini_set('error_log', $previousErrorLog);
        }
    }

    public function testConstructorWithParsedDsnArray(): void
    {
        $other = new DB(['scheme' => 'sqlite', 'path' => '/:memory:']);
        $query = $other->query('SELECT 1 as value');
        $query->exec();
        $row = $query->fetch();
        $this->assertSame('1', (string) $row['value']);
    }

    public function testUnsafeRawWhereAndJoinAreRejected(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->db->select('users', '1=1')->all();
    }

    public function testUnsafeRawJoinIsRejected(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->db->select('users', null, [
            'join' => ['JOIN profiles p ON p.user_id = users.id'],
        ])->all();
    }

    public function testTransactionExceptionType(): void
    {
        $this->assertTrue(is_a(TransactionException::class, \RuntimeException::class, true));
    }
}
