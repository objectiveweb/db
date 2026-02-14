<?php

include dirname(__DIR__) . '/vendor/autoload.php';

use Objectiveweb\DB;
use PHPUnit\Framework\TestCase;

class PgSQLCompatibilityTest extends TestCase
{
    private ?DB $db = null;

    protected function setUp(): void
    {
        $dsn = getenv('PGSQL_TEST_DSN');
        if (!$dsn) {
            $this->markTestSkipped('PGSQL_TEST_DSN not configured.');
        }

        $this->db = DB::connect(
            $dsn,
            getenv('PGSQL_TEST_USER') ?: 'postgres',
            getenv('PGSQL_TEST_PASSWORD') ?: 'postgres'
        );

        $this->db->query('DROP TABLE IF EXISTS pgsql_compat')->exec();
        $this->db->query('CREATE TABLE pgsql_compat (id SERIAL PRIMARY KEY, name VARCHAR(100), score INTEGER)')->exec();
    }

    public function testPgsqlCrudFlow(): void
    {
        $this->db->insert('pgsql_compat', ['name' => 'alice', 'score' => 10]);
        $this->db->insert('pgsql_compat', ['name' => 'bob', 'score' => 20]);

        $this->assertSame(2, $this->db->count('pgsql_compat'));

        $this->db->update('pgsql_compat', ['score' => 30], ['name' => 'bob']);
        $row = $this->db->select('pgsql_compat', ['name' => 'bob'])->fetch();
        $this->assertSame('30', (string) $row['score']);

        $this->assertSame(1, $this->db->delete('pgsql_compat', ['name' => 'alice']));
        $this->assertSame(1, $this->db->count('pgsql_compat'));
    }
}
