<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Exceptions\SessionException;

class SessionManager
{
    private ?string $id = null;
    private ?SessionBag $bag = null;
    private bool $started = false;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    private string|int|null $userId = null;

    public function __construct(
        private SessionDriverInterface $driver,
    ) {}

    public function start(?string $id = null): void
    {
        if ($this->started) {
            return;
        }

        $this->id = $id;

        if (!$this->id) {
            $this->id = $this->generateId();
        }

        // 1. Lock
        if (!$this->driver->lock($this->id)) {
            // The driver implementation of lock() has a timeout and retries.
            // If it returns false, it means timeout.
            throw SessionException::driverFailed('lock', 'Could not acquire session lock');
        }

        // 2. Read
        $data = $this->driver->read($this->id);

        if ($data) {
            // Existing session
            $payload = Serializer::unserialize($data['payload']);
            $flash = json_decode($data['flash_data'] ?? '[]', true);

            // Populate metadata on read so it persists/updates correctly
            $this->ipAddress = $data['ip_address'] ?? null;
            $this->userAgent = $data['user_agent'] ?? null;
            $this->userId = $data['user_id'] ?? null;
        } else {
            // New session
            $payload = [];
            $flash = [];
        }

        // 3. Initialize Bag
        $this->bag = new SessionBag($payload, $flash);
        $this->started = true;
    }

    public function save(): void
    {
        if (!$this->started || !$this->id) {
            return;
        }

        // Serialize Payload
        $payload = Serializer::serialize($this->bag->all());

        // Prepare Metadata
        $metadata = [
            'flash_data' => json_encode($this->bag->getNewFlash()),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'user_id'    => $this->userId,
        ];

        // Write
        $this->driver->write($this->id, $payload, $metadata);

        // Unlock
        $this->driver->unlock($this->id);

        $this->started = false;
    }

    public function regenerate(bool $destroy = false): bool
    {
        if (!$this->started) {
            return false;
        }

        $oldId = $this->id;

        // Generate new ID
        $this->id = $this->generateId();

        // If destroy is true, we delete the old session data completely
        if ($destroy) {
            $this->driver->destroy($oldId);
        } else {
            $this->driver->unlock($oldId);

            // Acquire lock for new ID immediately
            if (!$this->driver->lock($this->id)) {
                throw SessionException::driverFailed('lock', 'Could not acquire lock for regenerated session');
            }
        }

        return true;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBag(): ?SessionBag
    {
        return $this->bag;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    // -- Proxy methods to Bag for easier usage --

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bag?->get($key, $default) ?? $default;
    }

    public function set(string|array $key, mixed $value = null): void
    {
        $this->bag?->put($key, $value);
    }

    public function forget(string $key): void
    {
        $this->bag?->forget($key);
    }

    public function flash(string $key, mixed $value = true): void
    {
        $this->bag?->flash($key, $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->bag?->getFlash($key, $default) ?? $default;
    }

    public function reflash(): void
    {
        $this->bag?->reflash();
    }

    public function has(string $key): bool
    {
        return $this->bag?->has($key) ?? false;
    }

    // -- Metadata Setters --

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserId(string|int|null $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): string|int|null
    {
        return $this->userId;
    }
}
