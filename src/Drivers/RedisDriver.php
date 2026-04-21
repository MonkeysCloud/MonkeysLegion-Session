<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Drivers;

use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Exceptions\SessionException;
use MonkeysLegion\Session\Exceptions\SessionLockException;
use Redis;

/**
 * Redis session driver.
 */
class RedisDriver implements SessionDriverInterface
{
    private Redis $redis;
    private string $prefix;
    private int $ttl;
    
    /** @var array<string, string> */
    private array $locks = [];

    /**
     * @param Redis $redis The Redis connection instance
     * @param string $prefix Key prefix for session data (default: 'session:')
     * @param int $ttl Session time-to-live in seconds (default: 7200)
     */
    public function __construct(Redis $redis, string $prefix = 'session:', int $ttl = 7200)
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    public function __destruct()
    {
        try {
            if (!empty($this->locks)) {
                $this->close();
            }
        } catch (\Throwable) {
            // Silence errors in destructor
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        try {
            /** @var mixed $ping */
            $ping = $this->redis->ping();
            return (bool)$ping;
        } catch (\RedisException $e) {
            throw SessionException::driverFailed('open', $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        foreach (array_keys($this->locks) as $id) {
            $this->unlock((string)$id);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): ?array
    {
        try {
            $key = $this->getKey($id);

            /** @var array<string, mixed>|false $session */
            $session = $this->redis->hGetAll($key);

            if ($session === false || empty($session)) {
                return null;
            }

            $this->redis->expire($key, $this->ttl);

            return $session;
        } catch (\RedisException) {
             return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $payload, array $metadata): bool
    {
        try {
            $key = $this->getKey($id);

            $data = [
                'payload' => $payload,
                'last_activity' => time(),
            ];
            
            $data = array_merge($data, $metadata);

            /** @var bool|Redis $result */
            $result = $this->redis->hMSet($key, $data);
            
            $this->redis->expire($key, $this->ttl);

            return $result !== false;
        } catch (\RedisException $e) {
            throw SessionException::driverFailed('write', $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        try {
            $key = $this->getKey($id);
            $lockKey = $this->getLockKey($id);

            /** @var mixed $delResult */
            $delResult = $this->redis->del([$key, $lockKey]);

            unset($this->locks[$id]);

            return $delResult !== false;
        } catch (\RedisException $e) {
            throw SessionException::driverFailed('destroy', $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): int|false
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id, int $timeout = 30): bool
    {
        if (isset($this->locks[$id])) {
            throw SessionLockException::alreadyLocked($id);
        }

        $lockKey = $this->getLockKey($id);
        $lockValue = $this->generateLockValue();
        $deadline = microtime(true) + $timeout;

        try {
            $waitTime = 10000; // 10ms

            while (microtime(true) < $deadline) {
                /** @var bool|Redis $acquired */
                $acquired = $this->redis->set(
                    $lockKey,
                    $lockValue,
                    ['nx', 'ex' => $timeout + 5]
                );

                if ($acquired !== false) {
                    $this->locks[$id] = $lockValue;
                    return true;
                }

                usleep($waitTime);
                $waitTime = min($waitTime * 2, 100000); // Max 100ms
            }

            throw SessionLockException::timeout($id, $timeout);
        } catch (\RedisException $e) {
            throw SessionLockException::acquisitionFailed($id, $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string $id): bool
    {
        if (!isset($this->locks[$id])) {
            return true;
        }

        $lockKey = $this->getLockKey($id);
        $lockValue = $this->locks[$id];

        try {
            $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;

            /** @var mixed $result */
            $result = $this->redis->eval($script, [$lockKey, $lockValue], 1);

            unset($this->locks[$id]);

            return (int)$result > 0;
        } catch (\RedisException) {
            return false;
        }
    }

    private function getKey(string $id): string
    {
        return $this->prefix . $id;
    }

    private function getLockKey(string $id): string
    {
        return $this->prefix . 'lock:' . $id;
    }

    private function generateLockValue(): string
    {
        return uniqid((string)getmypid() . '_', true);
    }
    
    /**
     * Create a new session entry.
     *
     * @param string $id
     * @return bool
     */
    public function create(string $id): bool
    {
        try {
            $key = $this->getKey($id);
            
            $data = [
                'payload' => '',
                'last_activity' => time(),
                'created_at' => time(),
            ];

            /** @var bool|Redis $result */
            $result = $this->redis->hMSet($key, $data);
            $this->redis->expire($key, $this->ttl);

            return $result !== false;
        } catch (\RedisException) {
            return false;
        }
    }
}
