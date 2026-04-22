<?php

declare(strict_types=1);

use Objectiveweb\DB;

final class DbTestBootstrap
{
    public static function connect(): DB
    {
        return match (self::driver()) {
            'mysql' => new DB(
                getenv('MYSQL_TEST_DSN') ?: sprintf(
                    'mysql:dbname=%s;host=%s;port=%s;charset=utf8mb4',
                    getenv('MYSQL_TEST_DB') ?: 'objectiveweb_test',
                    getenv('MYSQL_TEST_HOST') ?: '127.0.0.1',
                    getenv('MYSQL_TEST_PORT') ?: '3306'
                ),
                getenv('MYSQL_TEST_USER') ?: 'root',
                getenv('MYSQL_TEST_PASSWORD') ?: 'root'
            ),
            'pgsql' => new DB(
                getenv('PGSQL_TEST_DSN') ?: sprintf(
                    'pgsql:dbname=%s;host=%s;port=%s',
                    getenv('PGSQL_TEST_DB') ?: 'objectiveweb_test',
                    getenv('PGSQL_TEST_HOST') ?: '127.0.0.1',
                    getenv('PGSQL_TEST_PORT') ?: '5432'
                ),
                getenv('PGSQL_TEST_USER') ?: 'postgres',
                getenv('PGSQL_TEST_PASSWORD') ?: 'postgres'
            ),
            default => new DB(getenv('SQLITE_TEST_DSN') ?: 'sqlite::memory:'),
        };
    }

    public static function createDbTestTable(DB $db, bool $withExtraFields): void
    {
        self::dropTable($db, 'db_test');

        $columns = ['name VARCHAR(255)'];
        if ($withExtraFields) {
            $columns[] = 'f1 VARCHAR(255)';
            $columns[] = 'f2 VARCHAR(255)';
            $columns[] = 'f3 VARCHAR(255)';
        }

        $db->query(sprintf(
            'CREATE TABLE db_test (%s, %s)',
            self::idDefinition(),
            implode(', ', $columns)
        ))->exec();
    }

    public static function createUsersAndProfiles(DB $db): void
    {
        self::dropTable($db, 'profiles');
        self::dropTable($db, 'users');

        $boolType = self::driver() === 'pgsql' ? 'BOOLEAN' : 'INTEGER';

        $db->query(sprintf(
            'CREATE TABLE users (%s, name VARCHAR(255), group_name VARCHAR(255), is_active %s)',
            self::idDefinition(),
            $boolType
        ))->exec();

        $db->query(sprintf(
            'CREATE TABLE profiles (%s, user_id INTEGER, city VARCHAR(255))',
            self::idDefinition()
        ))->exec();
    }

    public static function createThings(DB $db): void
    {
        self::dropTable($db, 'things');

        $db->query(sprintf(
            'CREATE TABLE things (%s, name VARCHAR(255), kind VARCHAR(255))',
            self::idDefinition()
        ))->exec();
    }

    public static function createInheritanceTables(DB $db): void
    {
        self::dropTable($db, 'things_ext');
        self::dropTable($db, 'things_base');

        $db->query(sprintf(
            'CREATE TABLE things_base (%s, common_name VARCHAR(255), common_kind VARCHAR(255))',
            self::idDefinition()
        ))->exec();

        $db->query(sprintf(
            'CREATE TABLE things_ext (%s, extra_value VARCHAR(255))',
            self::idDefinition()
        ))->exec();
    }

    public static function createInheritanceTablesWithCustomBaseKey(DB $db): void
    {
        self::dropTable($db, 'things_ext_key');
        self::dropTable($db, 'things_base_key');

        $db->query(sprintf(
            'CREATE TABLE things_base_key (%s, ext_id INTEGER, common_kind VARCHAR(255))',
            self::idDefinition()
        ))->exec();

        $db->query(sprintf(
            'CREATE TABLE things_ext_key (%s, extra_value VARCHAR(255))',
            self::idDefinition()
        ))->exec();
    }

    public static function driver(): string
    {
        return (string) (getenv('TEST_DB_DRIVER') ?: 'sqlite');
    }

    private static function dropTable(DB $db, string $table): void
    {
        $db->query(sprintf('DROP TABLE IF EXISTS %s', $table))->exec();
    }

    private static function idDefinition(): string
    {
        return match (self::driver()) {
            'mysql' => 'id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL',
            'pgsql' => 'id SERIAL PRIMARY KEY',
            default => 'id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL',
        };
    }
}
