<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests;

use MonkeysLegion\Session\EncryptedSerializer;
use MonkeysLegion\Session\NativeSerializer;
use PHPUnit\Framework\TestCase;

class EncryptedSerializerTest extends TestCase
{
    private array $keys;
    private NativeSerializer $nativeSerializer;
    private EncryptedSerializer $encryptedSerializer;

    protected function setUp(): void
    {
        $this->keys = [
            'v2' => str_repeat('b', 32), // Current key
            'v1' => str_repeat('a', 32), // Old key
        ];
        $this->nativeSerializer = new NativeSerializer();
        $this->encryptedSerializer = new EncryptedSerializer($this->nativeSerializer, $this->keys);
    }

    public function testPrepareAndRestore(): void
    {
        $data = ['user_id' => 123, 'roles' => ['admin', 'editor']];
        
        $prepared = $this->encryptedSerializer->prepare($data);
        
        $this->assertIsString($prepared);
        $decoded = json_decode($prepared, true);
        $this->assertArrayHasKey('iv', $decoded);
        $this->assertArrayHasKey('value', $decoded);
        $this->assertArrayHasKey('tag', $decoded);
        $this->assertArrayHasKey('key_id', $decoded);
        $this->assertEquals('v2', $decoded['key_id']);

        $restored = $this->encryptedSerializer->restore($prepared);
        $this->assertEquals($data, $restored);
    }

    public function testKeyRotation(): void
    {
        $data = ['foo' => 'bar'];
        
        // Encrypt with OLD key ring (where v1 is current)
        $oldKeys = ['v1' => str_repeat('a', 32)];
        $oldSerializer = new EncryptedSerializer($this->nativeSerializer, $oldKeys);
        $preparedWithOldKey = $oldSerializer->prepare($data);
        
        // Decrypt with NEW key ring (contains v1 but v2 is current)
        $restored = $this->encryptedSerializer->restore($preparedWithOldKey);
        
        $this->assertEquals($data, $restored);
    }

    public function testTamperingFails(): void
    {
        $data = ['secret' => 'data'];
        $prepared = $this->encryptedSerializer->prepare($data);
        $decoded = json_decode($prepared, true);
        
        // Tamper with the ciphertext
        $ciphertext = base64_decode($decoded['value']);
        $ciphertext[0] = $ciphertext[0] ^ "\xff"; // Flip bits
        $decoded['value'] = base64_encode($ciphertext);
        
        $tampered = json_encode($decoded);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        
        $this->encryptedSerializer->restore($tampered);
    }

    public function testInvalidKeyFails(): void
    {
        $data = ['foo' => 'bar'];
        $prepared = $this->encryptedSerializer->prepare($data);
        
        // Use a serializer with a completely different key
        $wrongKeys = ['v3' => str_repeat('c', 32)];
        $wrongSerializer = new EncryptedSerializer($this->nativeSerializer, $wrongKeys);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        
        $wrongSerializer->restore($prepared);
    }
}
