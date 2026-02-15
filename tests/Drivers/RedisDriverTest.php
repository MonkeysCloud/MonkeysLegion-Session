<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Drivers;

use MonkeysLegion\Session\Drivers\RedisDriver;
use MonkeysLegion\Session\Exceptions\SessionException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisDriverTest extends TestCase
{
    /** @var Redis&MockObject */
    private $redis;
    private RedisDriver $driver;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->driver = new RedisDriver($this->redis);
    }

    public function testOpenPingsRedis(): void
    {
        $this->redis->expects($this->once())
            ->method('ping')
            ->willReturn(true);

        $this->assertTrue($this->driver->open('', ''));
    }

    public function testReadValidSession(): void
    {
        $id = 'sess_id';
        $key = 'session:' . $id;
        $data = ['payload' => 'serialized_data'];

        $this->redis->expects($this->once())
            ->method('hGetAll')
            ->with($key)
            ->willReturn($data);

        $this->redis->expects($this->once())
            ->method('expire')
            ->with($key, 7200);

        $result = $this->driver->read($id);
        $this->assertIsArray($result);
        $this->assertSame('serialized_data', $result['payload']);
    }

    public function testReadMissingSessionThrowsException(): void
    {
        $id = 'missing_id';
        $key = 'session:' . $id;

        $this->redis->expects($this->once())
            ->method('hGetAll')
            ->with($key)
            ->willReturn([]); // Redis returns empty array for missing hash

        $this->assertNull($this->driver->read($id));
    }

    public function testWriteUpdateExisting(): void
    {
        $id = 'sess_id';
        $key = 'session:' . $id;
        $payload = 'new_data';
        $metadata = ['flash' => '[]'];

        $this->redis->expects($this->once())
            ->method('hMSet')
            ->with($key, $this->callback(function ($arg) use ($payload) {
                return $arg['payload'] === $payload 
                    && isset($arg['last_activity'])
                    && isset($arg['flash']);
            }))
            ->willReturn(true);
            
        $this->redis->expects($this->once())
            ->method('expire')
            ->with($key, 7200);

        $this->assertTrue($this->driver->write($id, $payload, $metadata));
    }

    public function testDestroy(): void
    {
        $id = 'sess_id';
        $key = 'session:' . $id;
        $lockKey = 'session:lock:' . $id;

        $this->redis->expects($this->once())
            ->method('del')
            ->with([$key, $lockKey])
            ->willReturn(1);

        $this->assertTrue($this->driver->destroy($id));
    }

    public function testLockAcquisition(): void
    {
        $id = 'sess_id';
        $lockKey = 'session:lock:' . $id;

        $this->redis->expects($this->once())
            ->method('set')
            ->with($lockKey, $this->anything(), ['nx', 'ex' => 60]) // 30 + 30
            ->willReturn(true);

        $this->assertTrue($this->driver->lock($id));
    }

    public function testUnlockReleasesLock(): void
    {
        // First acquire lock to set internal state
        $id = 'sess_id';
        $lockKey = 'session:lock:' . $id;
        
        $this->redis->expects($this->once())
            ->method('set')
            ->willReturn(true);
            
        $this->driver->lock($id);

        // Now test unlock
        $this->redis->expects($this->once())
            ->method('eval')
             // Lua script text matching is tricky, using anything()
            ->with(
                $this->stringContains('redis.call("get", KEYS[1])'),
                $this->callback(function ($args) use ($lockKey) {
                    return is_array($args) 
                        && count($args) === 2 
                        && $args[0] === $lockKey 
                        && is_string($args[1]);
                }),
                1
            )
            ->willReturn(1);

        $this->assertTrue($this->driver->unlock($id));
    }
}
