<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Drivers;

use MonkeysLegion\Query\Query\QueryBuilder;
use MonkeysLegion\Session\Drivers\DatabaseDriver;
use ReflectionProperty;

class TestDatabaseDriverTest extends DatabaseDriverTest
{
    // Override to return our TestDatabaseDriver which uses file locking
    protected function makeDriverWithRealQueryBuilder(QueryBuilder $qb): DatabaseDriver
    {
        // Use the connection manager since DatabaseDriver v2 requires it
        $driver = new TestDatabaseDriver($this->manager, ['table' => 'sessions']);

        $ref = new ReflectionProperty(DatabaseDriver::class, 'queryBuilder');
        $ref->setValue($driver, $qb);

        return $driver;
    }

    // Override the lock test to assertion that it DOES work (parent might skip it)
    public function testLockUnlock(): void
    {
        $driver = $this->makeDriverWithRealQueryBuilder($this->qb);

        $this->assertTrue($driver->lock('sess_id', 1));
        $this->assertTrue($driver->unlock('sess_id'));
    }
}
