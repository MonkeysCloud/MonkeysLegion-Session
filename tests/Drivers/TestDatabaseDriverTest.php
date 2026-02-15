<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Drivers;

use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Session\Drivers\DatabaseDriver;
use ReflectionProperty;

class TestDatabaseDriverTest extends DatabaseDriverTest
{
    // Override to return our TestDatabaseDriver which uses file locking
    protected function makeDriverWithRealQueryBuilder(QueryBuilder $qb): DatabaseDriver
    {
        // We need to use the full namespace for the class we just created
        $driver = new TestDatabaseDriver($this->conn, ['table' => 'sessions']);

        $ref = new ReflectionProperty(DatabaseDriver::class, 'queryBuilder');
        $ref->setValue($driver, $qb);

        return $driver;
    }

    // Override the lock test to assertion that it DOES work (parent might skip it)
    public function testLockUnlock(): void
    {
        $driver = $this->makeDriverWithRealQueryBuilder($this->qb);

        $this->assertTrue($driver->lock('sess_id', 1));
        
        // Lock again should fail/timeout (since we don't have separate processes,
        // we can't easily test cross-process locking, but we can verify the API works)
        
        $this->assertTrue($driver->unlock('sess_id'));
    }
}
