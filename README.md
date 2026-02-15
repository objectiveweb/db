# objectiveweb/db

Small database abstraction layer built on top of Doctrine DBAL.

## Install

```bash
composer require objectiveweb/db doctrine/dbal
```

## Getting started

```php
use Objectiveweb\DB;

$db = DB::connect('mysql:dbname=app;host=127.0.0.1', 'user', 'secret');

// Raw query
$db->query('CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))')->exec();

// Insert
$insertId = $db->insert('users', ['name' => 'Alice']);

// Update (table, values, conditions)
$affectedRows = $db->update('users', ['name' => 'Alice Smith'], ['id' => 1]);

// Select all rows
$rows = $db->select('users')->all();

// Select with IN
$rows = $db->select('users', ['id' => [1, 2, 3]])->all();

// Select with LIKE
$rows = $db->select('users', ['name' => 'Ali%'])->all();

// Select with SQL functions
$stats = $db->select('users', [], [
    'fields' => [
        'total' => 'COUNT(*)',
        'avg_age' => 'AVG(age)',
        'max_age' => 'MAX(age)',
    ],
])->fetch();

// Map by field
$byId = $db->select('users')->map('id');

// Fetch row by row
$query = $db->select('users');
while ($row = $query->fetch()) {
    // process
}

// Delete
$affectedRows = $db->delete('users', ['id' => 1]);

// Transaction
$db->transaction(function (DB $db) {
    $id = $db->insert('users', ['name' => 'Bob']);
    $db->update('users', ['name' => 'Bobby'], ['id' => $id]);

    return $id;
});
```

## CRUD operations

```php
use Objectiveweb\DB;

$db = DB::connect('mysql:dbname=app;host=127.0.0.1', 'user', 'secret');

$table = $db->table('users', [
    'pk' => 'id',
    'join' => [],
    'model' => null, // optional: class-string to map rows into objects
]);

// Insert
$id = $table->insert(['name' => 'Alice']);

// Select all rows (returns Objectiveweb\DB\Collection)
$data = $table->select();

// Filter/sort/range
$data = $table->select([], [
    'filter' => ['name' => 'Alice'],
    'sort' => [
        ['last_name', 'asc'],
        ['id', 'desc'],
    ],
    'range' => [0, 9],
]);

$count = count($data);
$total = $data->total();
$contentRange = $data->contentRange();

foreach ($data as $item) {
    $item['name'];
}

// Update by filter
$updated = $table->update(['name' => 'Alice'], ['name' => 'Alice Smith']);

// Update by ID
$updated = $table->update(1, ['name' => 'Alice Smith']);
```

`select($filter, $params)` rule: when `$params['filter']` is provided, it is merged with `$filter` (`array_merge($filter, $params['filter'])`), so keys in `$params['filter']` win on conflicts.

### Model mapping (optional)

```php
use Objectiveweb\DB\Model;

final class UserModel extends Model
{
    protected static array $validFields = ['name', 'age'];

    protected static array $creationRules = [
        'name' => [
            'required' => true,
            'filter' => FILTER_UNSAFE_RAW,
            'validate' => [self::class, 'validateName'],
        ],
        'age' => [
            'required' => true,
            'filter' => FILTER_VALIDATE_INT,
            'validate' => [self::class, 'validateAge'],
        ],
    ];

    public static function validateName(mixed $value): bool|string
    {
        return is_string($value) && strlen($value) >= 2;
    }

    public static function validateAge(mixed $value): bool|string
    {
        return is_int($value) && $value >= 0;
    }
}

$table = $db->table('users', ['model' => UserModel::class]);
$users = $table->select(); // Collection<UserModel>
$one = $table->get(1);    // UserModel

// create/update payload is auto-filtered/validated against the model
$table->insert(['name' => 'Alice', 'age' => 31, 'ignored' => 'x']); // "ignored" is dropped
```

## Extending `DB\\Table`

```php
use Objectiveweb\DB\Table;

class UserTable extends Table
{
    protected ?string $table = 'users';

    protected ?array $params = [
        'pk' => 'id',
        'join' => [],
    ];
}

$table = $db->table(UserTable::class);
$table->insert(['name' => 'Alice']);
```

## Filter grammar

`where` arrays support:

- equality: `['id' => 10]`
- negation: `['!status' => 'archived']`
- `LIKE`: `['name' => 'Jo%']`
- `IN`: `['id' => [1, 2, 3]]`
- null checks: `['deleted_at' => null]`, `['!deleted_at' => null]`

## SQL function fields

You can use SQL functions in `params['fields']` and alias them with array keys.

```php
$row = $db->select('orders', ['status' => 'paid'], [
    'fields' => [
        'total_orders' => 'COUNT(*)',
        'avg_total' => 'AVG(total)',
        'max_total' => 'MAX(total)',
    ],
])->fetch();
```

Grouped aggregate example:

```php
$rows = $db->select('orders', null, [
    'fields' => [
        'customer_id',
        'orders_count' => 'COUNT(*)',
        'avg_total' => 'AVG(total)',
    ],
    'group' => 'customer_id',
    'order' => 'customer_id ASC',
])->all();
```

## Joins

`select()` accepts joins via `params['join']`.

### Legacy join map (backward compatible)

```php
$rows = $db->select('person_links', [], [
    'join' => [
        'inner:people p1' => 'p1.id = person_links.parent_a_id',
        'left:people p2'  => 'p2.id = person_links.parent_b_id',
        'right:people p3' => 'p3.id = person_links.child_id',
        'full:people p4'  => 'p4.id = person_links.child_id',
        'cross:calendar c' => '',
        'festival f'      => 'f.id = person_links.child_id', // no prefix => plain JOIN (DB default)
    ],
]);
```

### Structured join list (recommended)

```php
$rows = $db->select('person_links', [], [
    'join' => [
        ['type' => 'inner', 'table' => 'people', 'alias' => 'p1', 'on' => 'p1.id = person_links.parent_a_id'],
        ['type' => 'left',  'table' => 'people', 'alias' => 'p2', 'on' => 'p2.id = person_links.parent_b_id'],
        ['type' => 'right', 'table' => 'people', 'alias' => 'p3', 'on' => 'p3.id = person_links.child_id'],
        ['type' => 'full',  'table' => 'people', 'alias' => 'p4', 'on' => 'p4.id = person_links.child_id'],
        ['type' => 'cross', 'table' => 'calendar', 'alias' => 'c'],
    ],
]);
```

Supported `type` values:
- `inner`
- `left`
- `right`
- `full` (rendered as `FULL OUTER JOIN`)
- `cross`

Dialect note:
- `RIGHT JOIN` is not supported by SQLite.
- `FULL OUTER JOIN` is PostgreSQL-only in this project test matrix.

## Notes

- Table and field identifiers are validated before SQL generation.
- Raw string `where` clauses and raw join fragments are intentionally rejected for safety.
- `Collection::render()` does not emit HTTP headers. Use `Collection::contentRange()` if you need a `Content-Range` response header.

## Migration notes

- Low-level internals moved from direct PDO usage to Doctrine DBAL.
- Pagination totals now use a dedicated `COUNT(*)` query instead of `SQL_CALC_FOUND_ROWS`.
- Transaction helpers throw typed exceptions (`TransactionException`) when begin/commit/rollback fails.
- Test suite defaults to SQLite in-memory, so local MySQL is no longer required.

## Stability policy

- Semantic Versioning is used for public APIs.
- Public stable APIs: `Objectiveweb\DB`, `Objectiveweb\DB\Table`, `Objectiveweb\DB\Collection`, `Objectiveweb\DB\Query`.
- Internal/private helpers in `DB` (identifier parsing/compilation methods) are not part of the public contract.

## Test matrix (SQLite + MySQL + PostgreSQL)

- Default local run (SQLite): `vendor/bin/phpunit --testsuite sqlite`
- MySQL run: `TEST_DB_DRIVER=mysql MYSQL_TEST_DSN=\"mysql:dbname=objectiveweb_test;host=127.0.0.1;port=3306;charset=utf8mb4\" MYSQL_TEST_USER=root MYSQL_TEST_PASSWORD=root vendor/bin/phpunit --testsuite mysql`
- PostgreSQL run: `TEST_DB_DRIVER=pgsql PGSQL_TEST_DSN=\"pgsql:dbname=objectiveweb_test;host=127.0.0.1;port=5432\" PGSQL_TEST_USER=postgres PGSQL_TEST_PASSWORD=postgres vendor/bin/phpunit --testsuite pgsql`

### Run all databases in Docker

```bash
./scripts/test-docker.sh
```
