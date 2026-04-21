<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Drivers;

use MonkeysLegion\Database\Connection\ConnectionManager;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Query\Query\QueryBuilder;
use MonkeysLegion\Session\Drivers\DatabaseDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatabaseDriverTest extends TestCase
{
    protected ConnectionInterface $conn;
    protected ConnectionManager $manager;
    protected QueryBuilder $qb;
    protected DatabaseDriver $driver;
    protected string $table = 'sessions';

    protected function setUp(): void
    {
        $this->manager = ConnectionManager::fromArray([
            'test' => [
                'driver' => 'sqlite',
                'memory' => true,
            ],
        ]);

        $this->conn = $this->manager->connection();
        $this->qb = new QueryBuilder($this->manager);

        // Create the table
        $this->conn->execute('CREATE TABLE sessions (
            session_id VARCHAR(255) PRIMARY KEY NOT NULL,
            payload TEXT,
            flash_data TEXT,
            created_at INTEGER NOT NULL,
            last_activity INTEGER NOT NULL,
            expiration INTEGER NOT NULL,
            user_id INTEGER NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL
        )');

        $this->driver = new DatabaseDriver($this->manager, ['table' => $this->table]);
    }

    #[Test]
    public function it_can_open_and_close_connection(): void
    {
        $this->assertTrue($this->driver->open('', ''));
        $this->assertTrue($this->driver->close());
    }

    #[Test]
    public function it_can_read_an_existing_session(): void
    {
        $now = time();
        $this->qb->table($this->table)->insert([
            'session_id' => 'sess_1',
            'payload' => 'payload_data',
            'created_at' => $now,
            'last_activity' => $now,
            'expiration' => $now + 3600,
        ]);

        $data = $this->driver->read('sess_1');

        $this->assertIsArray($data);
        $this->assertEquals('payload_data', $data['payload']);
    }

    #[Test]
    public function it_returns_null_for_non_existent_session(): void
    {
        $this->assertNull($this->driver->read('non_existent'));
    }

    #[Test]
    public function it_returns_null_and_destroys_expired_session(): void
    {
        $expired = time() - 3600;
        $this->qb->table($this->table)->insert([
            'session_id' => 'sess_expired',
            'payload' => 'expired_data',
            'created_at' => $expired - 3600,
            'last_activity' => $expired,
            'expiration' => $expired,
        ]);

        $this->assertNull($this->driver->read('sess_expired'));

        $count = $this->qb->table($this->table)->where('session_id', '=', 'sess_expired')->count();
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_can_write_new_session(): void
    {
        $metadata = [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ];

        $this->assertTrue($this->driver->write('sess_new', 'new_payload', $metadata));

        $session = $this->qb->table($this->table)->where('session_id', '=', 'sess_new')->first();

        $this->assertNotNull($session);
        $this->assertEquals('new_payload', $session['payload']);
        $this->assertEquals('127.0.0.1', $session['ip_address']);
        $this->assertEquals('PHPUnit', $session['user_agent']);
    }

    #[Test]
    public function it_can_update_existing_session(): void
    {
        $now = time();
        $this->qb->table($this->table)->insert([
            'session_id' => 'sess_update',
            'payload' => 'old_payload',
            'created_at' => $now,
            'last_activity' => $now,
            'expiration' => $now + 3600,
        ]);

        $this->assertTrue($this->driver->write('sess_update', 'updated_payload', []));

        $session = $this->qb->table($this->table)->where('session_id', '=', 'sess_update')->first();

        $this->assertNotNull($session);
        $this->assertEquals('updated_payload', $session['payload']);
    }

    #[Test]
    public function it_can_destroy_a_session(): void
    {
        $now = time();
        $this->qb->table($this->table)->insert([
            'session_id' => 'sess_destroy',
            'payload' => 'data',
            'created_at' => $now,
            'last_activity' => $now,
            'expiration' => $now + 3600,
        ]);

        $this->assertTrue($this->driver->destroy('sess_destroy'));

        $count = $this->qb->table($this->table)->where('session_id', '=', 'sess_destroy')->count();
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_can_perform_garbage_collection(): void
    {
        $expired = time() - 3600*3;
        $active = time();

        // Use individual inserts as some QueryBuilder implementations might not support bulk insert
        $this->qb->table($this->table)->insert(['session_id' => 'expired_1', 'payload' => '', 'created_at' => $expired, 'last_activity' => $expired, 'expiration' => $expired]);
        $this->qb->table($this->table)->insert(['session_id' => 'expired_2', 'payload' => '', 'created_at' => $expired, 'last_activity' => $expired, 'expiration' => $expired]);
        $this->qb->table($this->table)->insert(['session_id' => 'active_1', 'payload' => '', 'created_at' => $active, 'last_activity' => $active, 'expiration' => $active + 3600]);

        $deletedCount = $this->driver->gc(3600);

        $this->assertEquals(2, $deletedCount);

        $remainingCount = $this->qb->table($this->table)->count();
        $this->assertEquals(1, $remainingCount);
    }

    #[Test]
    public function it_can_lock_and_unlock(): void
    {
        $this->assertTrue($this->driver->lock('sess_id'));
        $this->assertTrue($this->driver->unlock('sess_id'));
    }
}
