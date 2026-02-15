<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Drivers;

use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Exceptions\SessionException;
use MonkeysLegion\Session\Exceptions\SessionLockException;
use Redis;

class RedisDriver implements SessionDriverInterface
{
    private Redis $redis;
    private string $prefix;
    private int $ttl;
    private array $locks = [];

    /**
     * Create a new Redis driver instance.
     *
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

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        // Redis doesn't need initialization like files or database connections
        // We just verify the connection is alive
        try {
            $this->redis->ping();
            return true;
        } catch (\RedisException $e) {
            throw SessionException::driverFailed('open', $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        // Release any remaining locks before closing
        foreach (array_keys($this->locks) as $id) {
            $this->unlock($id);
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

            // Use HGETALL to get all session fields
            $session = $this->redis->hGetAll($key);

            // If session doesn't exist, return null
            if (empty($session)) {
                return null;
            }

            // Refresh the TTL on read to keep active sessions alive
            $this->redis->expire($key, $this->ttl);

            return $session;
        } catch (\RedisException $e) {
             // In production might want to log this
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
            
            // Merge metadata
            $data = array_merge($data, $metadata);

            $result = $this->redis->hMSet($key, $data);
            
            // Set expiration time
            $this->redis->expire($key, $this->ttl);

            return $result;
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

            // Delete both session data and lock (if exists)
            $this->redis->del([$key, $lockKey]);

            // Remove from local lock tracking
            unset($this->locks[$id]);

            return true;
        } catch (\RedisException $e) {
            throw SessionException::driverFailed('destroy', $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): int|false
    {
        // Redis handles expiration automatically via TTL
        // We don't need manual garbage collection
        // Return 0 to indicate no manual cleanup was needed
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id, int $timeout = 30): bool
    {
        // If already locked by this instance, don't re-lock
        if (isset($this->locks[$id])) {
            throw SessionLockException::alreadyLocked($id);
        }

        $lockKey = $this->getLockKey($id);
        $lockValue = $this->generateLockValue();
        $deadline = microtime(true) + $timeout;

        try {
            // Try to acquire lock with exponential backoff
            $waitTime = 10000; // Start with 10ms

            while (microtime(true) < $deadline) {
                // SET NX EX: Set if Not eXists with EXpiration
                // This is atomic and prevents race conditions
                $acquired = $this->redis->set(
                    $lockKey,
                    $lockValue,
                    ['nx', 'ex' => $timeout + 30] // Lock expires slightly after timeout
                );

                if ($acquired) {
                    $this->locks[$id] = $lockValue;
                    return true;
                }

                // Exponential backoff: wait before retrying
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
            // Silently return true if not locked by this instance
            // This prevents errors during cleanup
            return true;
        }

        $lockKey = $this->getLockKey($id);
        $lockValue = $this->locks[$id];

        try {
            // Only delete the lock if it's still ours (compare-and-delete)
            // This prevents accidentally releasing someone else's lock
            $script = <<<'LUA'
                if redis.call("get", KEYS[1]) == ARGV[1] then
                    return redis.call("del", KEYS[1])
                else
                    return 0
                end
            LUA;

            $result = $this->redis->eval($script, [$lockKey, $lockValue], 1);

            unset($this->locks[$id]);

            return $result > 0;
        } catch (\RedisException $e) {
            throw SessionLockException::releaseFailed($id, $e->getMessage());
        }
    }

    /**
     * Get the full Redis key for a session ID.
     * Uses hash to store session metadata like DatabaseDriver.
     *
     * @param string $id
     * @return string
     */
    private function getKey(string $id): string
    {
        return $this->prefix . $id;
    }

    /**
     * Get the Redis key for a session lock.
     *
     * @param string $id
     * @return string
     */
    private function getLockKey(string $id): string
    {
        return $this->prefix . 'lock:' . $id;
    }

    /**
     * Generate a unique lock value to identify this lock owner.
     *
     * @return string
     */
    private function generateLockValue(): string
    {
        return uniqid(getmypid() . '_', true);
    }

    /**
     * Set the TTL for sessions.
     *
     * @param int $ttl Time-to-live in seconds
     * @return void
     */
    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * Get the current TTL setting.
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}
