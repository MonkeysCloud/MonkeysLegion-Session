<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Contracts\DataHandlerInterface;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Exceptions\SessionException;

/**
 * High-level session manager.
 */
class SessionManager
{
    private ?string $id = null;
    private ?SessionBag $bag = null;
    private bool $started = false;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    private string|int|null $userId = null;
    private readonly DataHandlerInterface $dataHandler;

    public function __construct(
        private readonly SessionDriverInterface $driver,
        ?DataHandlerInterface $dataHandler = null
    ) {
        $this->dataHandler = $dataHandler ?: new NativeSerializer();
    }

    public function start(?string $id = null): void
    {
        if ($this->started) {
            return;
        }

        $this->id = $id;

        if ($this->id === null) {
            $this->id = $this->generateId();
        }

        // 1. Lock
        if (!$this->driver->lock($this->id)) {
            throw SessionException::driverFailed('lock', 'Could not acquire session lock');
        }

        // 2. Read
        $data = $this->driver->read($this->id);

        /** @var array<string, mixed> $payload */
        $payload = [];
        /** @var array<string, mixed> $flash */
        $flash = [];

        if ($data !== null) {
            // Existing session
            try {
                /** @var mixed $rawPayload */
                $rawPayload = $data['payload'] ?? '';
                $restored = $this->dataHandler->restore(is_string($rawPayload) ? $rawPayload : '');
                if (is_array($restored)) {
                    /** @var array<string, mixed> $payload */
                    $payload = $restored;
                }
            } catch (\Throwable) {
                // Ignore restoration failures
            }
            
            /** @var mixed $rawFlash */
            $rawFlash = $data['flash_data'] ?? '[]';
            $decoded = json_decode(is_string($rawFlash) ? $rawFlash : '[]', true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $flash */
                $flash = $decoded;
            }

            // Populate metadata
            $this->ipAddress = (isset($data['ip_address']) && is_string($data['ip_address'])) ? $data['ip_address'] : null;
            $this->userAgent = (isset($data['user_agent']) && is_string($data['user_agent'])) ? $data['user_agent'] : null;
            
            /** @var mixed $userId */
            $userId = $data['user_id'] ?? null;
            $this->userId = (is_string($userId) || is_int($userId)) ? $userId : null;
        }

        // 3. Initialize Bag
        $this->bag = new SessionBag($payload, $flash);

        // 4. Ensure CSRF Token exists
        if (!$this->has('_token')) {
            $this->regenerateToken();
        }

        $this->started = true;
    }

    public function save(): void
    {
        if (!$this->started || $this->id === null || $this->bag === null) {
            return;
        }

        $payload = $this->dataHandler->prepare($this->bag->all());

        $metadata = [
            'flash_data' => json_encode($this->bag->getNewFlash()),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'user_id'    => $this->userId,
        ];

        $this->driver->write($this->id, $payload, $metadata);
        $this->driver->unlock($this->id);

        $this->started = false;
    }

    public function regenerate(bool $destroy = false): bool
    {
        if (!$this->started || $this->id === null) {
            return false;
        }

        $oldId = $this->id;
        $this->id = $this->generateId();

        if ($destroy) {
            $this->driver->destroy($oldId);
        } else {
            $this->driver->unlock($oldId);

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

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bag?->get($key, $default) ?? $default;
    }

    /**
     * @param string|array<string, mixed> $key
     * @param mixed $value
     */
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

    public function token(): string
    {
        $token = $this->get('_token', '');
        return is_string($token) ? $token : '';
    }

    public function regenerateToken(): void
    {
        $this->set('_token', bin2hex(random_bytes(40)));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->bag?->all() ?? [];
    }
}
