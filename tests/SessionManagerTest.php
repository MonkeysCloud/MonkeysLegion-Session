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
            // Any ID check
            ->willReturn(null); // New session

        $this->manager->start();
        $this->assertTrue($this->manager->isStarted());
        $this->assertNotNull($this->manager->getId());
    }

    public function testStartExistingSession(): void
    {
        $id = 'existing_sess';
        $payload = serialize(['key' => 'val']);

        $this->driver->expects($this->once())
            ->method('lock')
            ->with($id)
            ->willReturn(true);

        $this->driver->expects($this->once())
            ->method('read')
            ->with($id)
            ->willReturn(['payload' => $payload, 'flash_data' => '[]']);

        $this->manager->start($id);

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
                    return unserialize($p)['foo'] === 'bar';
                }),
                $this->anything()
            )
            ->willReturn(true);

        $this->driver->expects($this->once())
            ->method('unlock');

        $this->manager->save();
        $this->assertFalse($this->manager->isStarted());
    }
}
