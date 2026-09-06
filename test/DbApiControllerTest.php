<?php

declare(strict_types=1);

use Objectiveweb\Controller\DbRowsController;
use Objectiveweb\DB\Api\DatabaseRegistry;
use Objectiveweb\DB\Api\DbApiAuthorizer;
use Objectiveweb\DB\Api\DbApiService;
use PHPUnit\Framework\TestCase;

final class DbApiControllerTest extends TestCase
{
    public function testRowsControllerSanitizesQueryAndDelegatesToService(): void
    {
        $db = DbTestBootstrap::connect();
        DbTestBootstrap::createUsersAndProfiles($db);
        $db->table('users')->insert(['name' => 'Ada']);

        $received = null;
        $controller = new DbRowsController(
            new DbApiService(new DatabaseRegistry(['app' => $db])),
            new DbApiAuthorizer(static function (array $context) use (&$received): bool {
                $received = $context['query'];
                return true;
            }),
            'app',
            'users'
        );

        $result = $controller->index(['filter' => '{"name":"Ada"}', 'ignored' => 'discard']);

        self::assertSame(['filter' => '{"name":"Ada"}'], $received);
        self::assertSame(1, $result['total']);
        self::assertSame('Ada', $result['data'][0]['name']);
    }
}
