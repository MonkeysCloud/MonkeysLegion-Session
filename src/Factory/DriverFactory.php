<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Factory;

use InvalidArgumentException;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Drivers\DatabaseDriver;
use MonkeysLegion\Session\Drivers\FileDriver;
use MonkeysLegion\Session\Drivers\RedisDriver;
use Redis;
use RuntimeException;

class DriverFactory
{
    /**
     * Create a new session driver instance.
     *
     * @param string $driver The driver name ('file', 'database', 'redis')
     * @param array $config Configuration array for the driver
     * @return SessionDriverInterface
     * @throws InvalidArgumentException If the driver is not supported
     * @throws RuntimeException If required dependencies are missing in config
     */
    public function make(string $driver, array $config): SessionDriverInterface
    {
        return match ($driver) {
            'file' => $this->createFileDriver($config),
            'database' => $this->createDatabaseDriver($config),
            'redis' => $this->createRedisDriver($config),
            default => throw new InvalidArgumentException("Unsupported session driver: {$driver}"),
        };
    }

    protected function createFileDriver(array $config): FileDriver
    {
        if (!isset($config['path'])) {
            throw new RuntimeException("File driver requires 'path' configuration.");
        }

        $ttl = $config['lifetime'] ?? 7200;

        return new FileDriver($config['path'], (int)$ttl);
    }

    protected function createDatabaseDriver(array $config): DatabaseDriver
    {
        if (!isset($config['connection']) || !($config['connection'] instanceof ConnectionInterface)) {
            throw new RuntimeException("Database driver requires 'connection' (ConnectionInterface) in configuration.");
        }

        return new DatabaseDriver($config['connection'], $config);
    }

    protected function createRedisDriver(array $config): RedisDriver
    {
        if (!isset($config['redis']) || !($config['redis'] instanceof Redis)) {
            throw new RuntimeException("Redis driver requires 'redis' (Redis) instance in configuration.");
        }

        $prefix = $config['prefix'] ?? 'session:';
        $ttl = $config['lifetime'] ?? 7200;

        return new RedisDriver($config['redis'], $prefix, (int)$ttl);
    }
}
