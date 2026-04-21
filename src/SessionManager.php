<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Contracts\DataHandlerInterface;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Contracts\SessionInterface;
use MonkeysLegion\Session\Exceptions\SessionException;

class SessionManager implements SessionInterface
{
    private ?string $sessionId = null;
    private ?SessionBag $bag = null;
    private bool $sessionStarted = false;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    private string|int|null $userId = null;
    private DataHandlerInterface $dataHandler;

    public function __construct(
        private readonly SessionDriverInterface $driver,
        ?DataHandlerInterface $dataHandler = null
    ) {
        $this->dataHandler = $dataHandler ?: new NativeSerializer();
    }

    public string $id {
        get => $this->sessionId ?: '';
        set(string $value) {
            if ($this->sessionStarted) {
                throw SessionException::alreadyStarted();
            }
            $this->sessionId = $value;
        }
    }

    public bool $isStarted {
        get => $this->sessionStarted;
    }

    public function start(?string $id = null): bool
    {
        if ($this->sessionStarted) {
            return true;
        }

        if ($id !== null && $id !== '') {
            $this->sessionId = $id;
        }

        if (!$this->sessionId) {
            $this->sessionId = $this->generateId();
        }

        // 1. Lock
        if (!$this->driver->lock($this->sessionId)) {
            throw SessionException::driverFailed('lock', 'Could not acquire session lock');
        }

        // 2. Read and initialize
        try {
            $data = $this->driver->read($this->sessionId);

            if ($data) {
                // Existing session
                try {
                    /** @var string $payloadRaw */
                    $payloadRaw = $data['payload'] ?? '';
                    /** @var array<string, mixed>|null $payload */
                    $payload = $this->dataHandler->restore($payloadRaw);
                    if ($payload === null) {
                        $payload = [];
                    }
                } catch (\Throwable) {
                    $payload = [];
                }
                
                /** @var string $flashRaw */
                $flashRaw = $data['flash_data'] ?? '[]';
                /** @var array<string, mixed> $flash */
                $flash = json_decode($flashRaw, true) ?: [];

                // Use existing values if not set during this process
                $this->ipAddress ??= $data['ip_address'] ?? null;
                $this->userAgent ??= $data['user_agent'] ?? null;
                $this->userId ??= $data['user_id'] ?? null;
            } else {
                // New session
                $payload = [];
                $flash = [];
            }

            // 3. Initialize Bag
            $this->bag = new SessionBag($payload, $flash);
            $this->sessionStarted = true; // Set early to avoid recursion in has()

            // 4. Ensure CSRF Token exists
            if (!$this->has('_token')) {
                $this->regenerateToken();
            }

            return true;
        } catch (\Throwable $e) {
            $this->driver->unlock($this->sessionId);
            throw $e;
        }
    }

    public function isStarted(): bool
    {
        return $this->sessionStarted;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function save(): bool
    {
        if (!$this->sessionStarted || !$this->sessionId) {
            return false;
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $this->bag?->all() ?? [];
        $payload = $this->dataHandler->prepare($attributes);

        $metadata = [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'user_id'    => $this->userId,
            'flash_data' => json_encode($this->bag?->getNewFlash() ?? []),
        ];

        // Write
        $this->driver->write($this->sessionId, $payload, $metadata);

        // Unlock
        $this->driver->unlock($this->sessionId);

        $this->sessionStarted = false;
        return true;
    }

    public function regenerate(bool $destroy = false): bool
    {
        if (!$this->sessionStarted) {
            return false;
        }

        $oldId = $this->sessionId;

        // Generate new ID
        $newId = $this->generateId();

        // If destroy is true, we delete the old session data completely
        if ($destroy) {
            $this->driver->destroy($oldId);
        } else {
            $this->driver->unlock($oldId);
        }

        $this->sessionId = $newId;

        // Acquire lock for new ID immediately
        if (!$this->driver->lock($this->sessionId)) {
            throw SessionException::driverFailed('lock', 'Could not acquire lock for regenerated session');
        }

        return true;
    }

    public function invalidate(): bool
    {
        if (!$this->sessionStarted) {
            $this->start();
        }

        $this->bag?->flush();
        return $this->regenerate(true);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(20));
    }

    // -- Proxy methods to Bag for easier usage --

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->sessionStarted) { $this->start(); }
        return $this->bag?->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        if (!$this->sessionStarted) { $this->start(); }
        $this->bag?->put($key, $value);
    }

    public function has(string $key): bool
    {
        if (!$this->sessionStarted) { $this->start(); }
        return $this->bag?->has($key) ?? false;
    }

    public function forget(string $key): void
    {
        if (!$this->sessionStarted) { $this->start(); }
        $this->bag?->forget($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        if (!$this->sessionStarted) { $this->start(); }
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        if (!$this->sessionStarted) { $this->start(); }
        $this->bag?->flash($key, $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!$this->sessionStarted) { $this->start(); }
        return $this->bag?->getFlash($key, $default);
    }

    public function reflash(): void
    {
        if (!$this->sessionStarted) { $this->start(); }
        $this->bag?->reflash();
    }

    public function keep(string ...$keys): void
    {
        if (!$this->sessionStarted) { $this->start(); }
        $this->bag?->keep($keys);
    }

    public function now(string $key, mixed $value): void
    {
        if (!$this->sessionStarted) { $this->start(); }
        $this->bag?->flash($key, $value);
    }

    public function all(): array
    {
        if (!$this->sessionStarted) { $this->start(); }
        return $this->bag?->all() ?? [];
    }

    public function token(): string
    {
        return (string) $this->get('_token', '');
    }

    public function regenerateToken(): void
    {
        $this->set('_token', bin2hex(random_bytes(40)));
    }

    // -- For Middleware / External usage --

    public function setRequestInfo(?string $ip, ?string $userAgent): void
    {
        $this->ipAddress = $ip;
        $this->userAgent = $userAgent;
    }

    public function setIpAddress(?string $ip): void
    {
        $this->ipAddress = $ip;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function setUserId(string|int|null $userId): void
    {
        $this->userId = $userId;
    }
}
