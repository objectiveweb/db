<?php

include dirname(__DIR__) . '/vendor/autoload.php';

use Objectiveweb\DB;
use PHPUnit\Framework\TestCase;

class MySQLCompatibilityTest extends TestCase
{
    private ?DB $db = null;

    protected function setUp(): void
    {
        $dsn = getenv('MYSQL_TEST_DSN');
        if (!$dsn) {
            $this->markTestSkipped('MYSQL_TEST_DSN not configured.');
        }

        $this->db = new DB(
            $dsn,
            getenv('MYSQL_TEST_USER') ?: 'root',
            getenv('MYSQL_TEST_PASSWORD') ?: 'root'
        );

        $this->db->query('DROP TABLE IF EXISTS mysql_compat')->exec();
        $this->db->query('CREATE TABLE mysql_compat (id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL, name VARCHAR(100), score INT)')->exec();
    }

    public function testMysqlCrudFlow(): void
    {
        $this->db->insert('mysql_compat', ['name' => 'alice', 'score' => 10]);
        $this->db->insert('mysql_compat', ['name' => 'bob', 'score' => 20]);

        $this->assertSame(2, $this->db->count('mysql_compat'));

        $this->db->update('mysql_compat', ['score' => 30], ['name' => 'bob']);
        $row = $this->db->select('mysql_compat', ['name' => 'bob'])->fetch();
        $this->assertSame('30', (string) $row['score']);

        $this->assertSame(1, $this->db->delete('mysql_compat', ['name' => 'alice']));
        $this->assertSame(1, $this->db->count('mysql_compat'));
    }
}
