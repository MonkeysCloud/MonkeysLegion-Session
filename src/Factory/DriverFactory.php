<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Factory;

use InvalidArgumentException;
use MonkeysLegion\Database\Contracts\ConnectionManagerInterface;
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
     * @param array<string, mixed> $config Configuration array for the driver
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

    /**
     * @param array<string, mixed> $config
     */
    protected function createFileDriver(array $config): FileDriver
    {
        if (!isset($config['path'])) {
            throw new RuntimeException("File driver requires 'path' configuration.");
        }

        /** @var mixed $path */
        $path = $config['path'];
        /** @var mixed $ttlVal */
        $ttlVal = $config['lifetime'] ?? 7200;
        $ttl = is_numeric($ttlVal) ? (int)$ttlVal : 7200;

        return new FileDriver(is_string($path) ? $path : '', $ttl);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createDatabaseDriver(array $config): DatabaseDriver
    {
        if (!isset($config['connection']) || !($config['connection'] instanceof ConnectionManagerInterface)) {
            throw new RuntimeException("Database driver requires 'connection' (ConnectionManagerInterface) in configuration.");
        }

        return new DatabaseDriver($config['connection'], $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createRedisDriver(array $config): RedisDriver
    {
        if (!isset($config['redis']) || !($config['redis'] instanceof Redis)) {
            throw new RuntimeException("Redis driver requires 'redis' (Redis) instance in configuration.");
        }

        /** @var mixed $prefixVal */
        $prefixVal = $config['prefix'] ?? 'session:';
        $prefix = is_string($prefixVal) ? $prefixVal : 'session:';
        
        /** @var mixed $ttlVal */
        $ttlVal = $config['lifetime'] ?? 7200;
        $ttl = is_numeric($ttlVal) ? (int)$ttlVal : 7200;

        return new RedisDriver($config['redis'], $prefix, $ttl);
    }
}
