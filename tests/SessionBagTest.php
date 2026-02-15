<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests;

use MonkeysLegion\Session\SessionBag;
use PHPUnit\Framework\TestCase;

class SessionBagTest extends TestCase
{
    public function testGetAndPut(): void
    {
        $bag = new SessionBag();
        $bag->put('key', 'value');
        $this->assertEquals('value', $bag->get('key'));
        
        $bag->put(['key2' => 'value2', 'key3' => 'value3']);
        $this->assertEquals('value2', $bag->get('key2'));
        $this->assertEquals('value3', $bag->get('key3'));
    }

    public function testDotNotation(): void
    {
        $bag = new SessionBag();
        $bag->put('user.name', 'John');
        $bag->put('user.email', 'john@example.com');

        $this->assertEquals('John', $bag->get('user.name'));
        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com'], $bag->get('user'));
    }

    public function testForget(): void
    {
        $bag = new SessionBag(['key' => 'value']);
        $bag->forget('key');
        $this->assertNull($bag->get('key'));
        
        $bag->put('nested.item', 'exists');
        $bag->forget('nested.item');
        $this->assertNull($bag->get('nested.item'));
        $this->assertNotNull($bag->get('nested')); // parent should remain
    }

    public function testFlash(): void
    {
        $bag = new SessionBag();
        $bag->flash('status', 'success');

        // New flash should be available in getNewFlash()
        $this->assertArrayHasKey('status', $bag->getNewFlash());
        $this->assertEquals('success', $bag->getNewFlash()['status']);

        // And immediately available via getFlash (simulating same request)
        $this->assertEquals('success', $bag->getFlash('status'));
    }
}
