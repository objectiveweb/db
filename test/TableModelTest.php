<?php

declare(strict_types=1);

include dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/DbTestBootstrap.php';

use Objectiveweb\DB;
use Objectiveweb\DB\Exception\InvalidQueryException;
use Objectiveweb\DB\Exception\ModelValidationException;
use Objectiveweb\DB\Model;
use PHPUnit\Framework\TestCase;

final class TableModelCtor extends Model
{
    public string $name;
    public int $id;

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
    }
}

final class TableValidatedModel extends Model
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
        if (!is_string($value) || strlen(trim($value)) < 2) {
            return 'name must have at least 2 chars';
        }

        return true;
    }

    public static function validateAge(mixed $value): bool|string
    {
        if (!is_int($value) || $value < 0) {
            return 'age must be a non-negative integer';
        }

        return true;
    }
}

class TableModelTest extends TestCase
{
    private DB $db;

    protected function setUp(): void
    {
        $this->db = DbTestBootstrap::connect();

        $this->db->query('DROP TABLE IF EXISTS model_test')->exec();
        $this->db->query(sprintf('CREATE TABLE model_test (%s, name VARCHAR(255), age INTEGER)', $this->idDefinition()))->exec();

        $this->db->insert('model_test', ['name' => 'first', 'age' => 10]);
        $this->db->insert('model_test', ['name' => 'second', 'age' => 20]);
    }

    public function testTableReturnsModelObjectsUsingConstructorArray(): void
    {
        $table = $this->db->table('model_test', ['model' => TableModelCtor::class]);

        $rows = $table->select();
        $this->assertInstanceOf(TableModelCtor::class, $rows[0]);
        $this->assertSame('first', $rows[0]->name);

        $single = $table->get(2);
        $this->assertInstanceOf(TableModelCtor::class, $single);
        $this->assertSame(2, $single->id);
    }

    public function testInvalidModelClassThrows(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->db->table('model_test', ['model' => 'NotARealModelClass']);
    }

    public function testValidatedModelFiltersAndValidatesOnCreate(): void
    {
        $table = $this->db->table('model_test', ['model' => TableValidatedModel::class]);
        $id = $table->insert(['name' => 'third', 'age' => 30, 'ignored' => 'x']);

        $this->assertNotNull($id);

        $row = $this->db->select('model_test', ['id' => (int) $id['id']])->fetch();
        $this->assertSame('third', $row['name']);
        $this->assertSame('30', (string) $row['age']);
    }

    public function testValidatedModelRejectsInvalidCreatePayload(): void
    {
        $table = $this->db->table('model_test', ['model' => TableValidatedModel::class]);

        $this->expectException(ModelValidationException::class);
        $table->insert(['age' => 30]);
    }

    public function testValidatedModelSupportsModelInstancePayload(): void
    {
        $table = $this->db->table('model_test', ['model' => TableValidatedModel::class]);
        $model = new TableValidatedModel(['name' => 'fourth', 'age' => 44]);

        $id = $table->insert($model);
        $this->assertNotNull($id);
    }

    public function testValidatedModelRejectsUpdateWithoutValidFields(): void
    {
        $table = $this->db->table('model_test', ['model' => TableValidatedModel::class]);

        $this->expectException(ModelValidationException::class);
        $table->update(1, ['ignored' => 'x']);
    }

    public function testValidatedModelRejectsNegativeAgeWithCustomValidator(): void
    {
        $table = $this->db->table('model_test', ['model' => TableValidatedModel::class]);

        $this->expectException(ModelValidationException::class);
        $table->insert(['name' => 'bad', 'age' => -1]);
    }

    private function idDefinition(): string
    {
        return match ((string) (getenv('TEST_DB_DRIVER') ?: 'sqlite')) {
            'mysql' => 'id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL',
            'pgsql' => 'id SERIAL PRIMARY KEY',
            default => 'id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL',
        };
    }
}
