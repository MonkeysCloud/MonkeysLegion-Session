<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests;

use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    /** @var SessionDriverInterface&MockObject */
    private $driver;
    private SessionManager $manager;

    protected function setUp(): void
    {
        $this->driver = $this->createMock(SessionDriverInterface::class);
        $this->manager = new SessionManager($this->driver);
    }

    public function testStartNewSession(): void
    {
        $this->driver->expects($this->once())
            ->method('lock')
            ->willReturn(true);

        $this->driver->expects($this->once())
            ->method('read')
            ->willReturn(null); // New session

        $this->manager->start();
        $this->assertTrue($this->manager->isStarted());
        $this->assertNotNull($this->manager->getId());
    }

    public function testStartExistingSession(): void
    {
        $id = 'existing_sess';
        // v2 format: payload is just the attributes array
        $payload = serialize(['key' => 'val']);

        $this->driver->expects($this->once())
            ->method('lock')
            ->with($id)
            ->willReturn(true);

        $this->driver->expects($this->once())
            ->method('read')
            ->with($id)
            ->willReturn(['payload' => $payload, 'flash_data' => '[]']);

        $this->manager->id = $id;
        $this->manager->start();

        $this->assertEquals('val', $this->manager->get('key'));
    }

    public function testSave(): void
    {
        $this->driver->method('lock')->willReturn(true);
        $this->driver->method('read')->willReturn(null);

        $this->manager->start();
        $this->manager->set('foo', 'bar');

        $this->driver->expects($this->once())
            ->method('write')
            ->with(
                $this->anything(),
                $this->callback(function ($p) {
                    $unserialized = unserialize($p);
                    // v2 format: NO _attributes wrapper
                    return isset($unserialized['foo']) && $unserialized['foo'] === 'bar';
                }),
                $this->anything()
            )
            ->willReturn(true);

        $this->driver->expects($this->once())
            ->method('unlock')
            ->willReturn(true);

        $this->manager->save();
        $this->assertFalse($this->manager->isStarted());
    }
}
