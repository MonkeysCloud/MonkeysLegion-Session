<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Contracts\DataHandlerInterface;
use MonkeysLegion\Session\Exceptions\SessionException;
use RuntimeException;

/**
 * Encrypted serializer wrapper.
 */
class EncryptedSerializer implements DataHandlerInterface
{
    private const string CIPHER = 'aes-256-gcm';

    /**
     * @param DataHandlerInterface $serializer The underlying serializer (e.g., NativeSerializer)
     * @param array<string, string> $keys The key ring (ID => Key)
     */
    public function __construct(
        private readonly DataHandlerInterface $serializer,
        private readonly array $keys
    ) {
        if (empty($this->keys)) {
            throw new RuntimeException('Encryption requires at least one key in the key ring.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(mixed $data): string
    {
        $serialized = $this->serializer->prepare($data);
        
        $ivLength = (int)openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';
        
        // Get the current (first) key and its ID
        $keyId = (string)array_key_first($this->keys);
        $key = $this->keys[$keyId];

        $ciphertext = openssl_encrypt(
            $serialized,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw SessionException::driverFailed('encrypt', 'Encryption failed');
        }

        return (string)json_encode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
            'key_id' => $keyId,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function restore(string $data): mixed
    {
        /** @var array<string, string>|null $payload */
        $payload = json_decode($data, true);

        if ($payload === null || !isset($payload['iv'], $payload['value'], $payload['tag'])) {
            throw new RuntimeException('Invalid encrypted payload structure.');
        }

        $iv = base64_decode($payload['iv']);
        $ciphertext = base64_decode($payload['value']);
        $tag = base64_decode($payload['tag']);
        $keyId = $payload['key_id'] ?? null;

        $key = null;
        if ($keyId !== null && isset($this->keys[$keyId])) {
            $key = $this->keys[$keyId];
        }

        if ($key !== null) {
            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($decrypted !== false) {
                return $this->serializer->restore($decrypted);
            }
        }

        // If Key ID was wrong or decryption failed, try all keys in the ring (legacy/fallback)
        foreach ($this->keys as $candidateKeyId => $candidateKey) {
            if ($keyId !== null && (string)$candidateKeyId === (string)$keyId) {
                continue;
            }

            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $candidateKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($decrypted !== false) {
                return $this->serializer->restore($decrypted);
            }
        }

        throw new RuntimeException('Decryption failed: No valid key found or data tampered.');
    }
}
