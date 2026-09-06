<?php

declare(strict_types=1);

use Objectiveweb\DB\Api\ApiException;
use Objectiveweb\DB\Api\DatabaseRegistry;
use Objectiveweb\DB\Api\DbApi;
use PHPUnit\Framework\TestCase;

final class DbApiTest extends TestCase
{
    private DbApi $api;

    protected function setUp(): void
    {
        $db = DbTestBootstrap::connect();
        DbTestBootstrap::createUsersAndProfiles($db);
        $db->query('CREATE TABLE read_only (name VARCHAR(255))')->exec();
        $this->api = new DbApi(new DatabaseRegistry(['app' => $db]), static fn (): bool => true);
    }

    public function testCrudFlowUsesSchemaValidatedServiceLayer(): void
    {
        $created = $this->api->create('app', 'users', ['name' => 'Ada']);
        $rows = $this->api->rows('app', 'users', ['filter' => '{"name":"Ada"}', 'sort' => 'id:asc']);
        $this->assertSame(1, $rows['total']);
        $this->assertSame('Ada', $rows['data'][0]['name']);

        $updated = $this->api->update('app', 'users', (string) $created['id'], ['name' => 'Grace']);
        $this->assertSame('Grace', $updated['name']);
        $this->assertSame(['deleted' => 1], $this->api->delete('app', 'users', (string) $created['id']));
    }

    public function testRowsRequireASingleColumnPrimaryKey(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(405);
        $this->api->rows('app', 'read_only');
    }

    public function testAuthorizationAndUnknownFiltersAreRejected(): void
    {
        $denied = new DbApi(new DatabaseRegistry(['app' => DbTestBootstrap::connect()]), static fn (): bool => false);
        try {
            $denied->databases();
            self::fail('Expected authorization to fail.');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getCode());
        }

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);
        $this->api->rows('app', 'users', ['filter' => '{"unknown":"value"}']);
    }
}
