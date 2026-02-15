<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Factory;

use InvalidArgumentException;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Session\Drivers\DatabaseDriver;
use MonkeysLegion\Session\Drivers\FileDriver;
use MonkeysLegion\Session\Drivers\RedisDriver;
use MonkeysLegion\Session\Factory\DriverFactory;
use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;

class DriverFactoryTest extends TestCase
{
    private DriverFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DriverFactory();
    }

    public function testMakeFileDriver(): void
    {
        $path = sys_get_temp_dir() . '/ml_sess_factory';
        $driver = $this->factory->make('file', [
            'path' => $path,
            'lifetime' => 3600
        ]);

        $this->assertInstanceOf(FileDriver::class, $driver);
    }

    public function testMakeFileDriverThrowsExceptionWithoutPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("File driver requires 'path' configuration.");
        
        $this->factory->make('file', []);
    }

    public function testMakeDatabaseDriver(): void
    {
        $conn = $this->createMock(ConnectionInterface::class);
        $driver = $this->factory->make('database', [
            'connection' => $conn,
            'table' => 'sessions'
        ]);

        $this->assertInstanceOf(DatabaseDriver::class, $driver);
    }

    public function testMakeDatabaseDriverThrowsExceptionWithoutConnection(): void
    {
        $this->expectException(RuntimeException::class);
        
        $this->factory->make('database', []);
    }

    public function testMakeRedisDriver(): void
    {
        $redis = $this->createMock(Redis::class);
        $driver = $this->factory->make('redis', [
            'redis' => $redis,
            'prefix' => 'sess:',
            'lifetime' => 3600
        ]);

        $this->assertInstanceOf(RedisDriver::class, $driver);
    }

    public function testMakeRedisDriverThrowsExceptionWithoutRedis(): void
    {
        $this->expectException(RuntimeException::class);
        
        $this->factory->make('redis', []);
    }

    public function testMakeThrowsExceptionGeneric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported session driver: unsupported");

        $this->factory->make('unsupported', []);
    }
}
