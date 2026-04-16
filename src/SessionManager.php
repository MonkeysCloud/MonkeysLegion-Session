<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Bags\AttributeBag;
use MonkeysLegion\Session\Bags\FlashBag;
use MonkeysLegion\Session\Bags\MetadataBag;
use MonkeysLegion\Session\Contracts\DataHandlerInterface;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Contracts\SessionInterface;
use MonkeysLegion\Session\Exceptions\SessionException;

class SessionManager implements SessionInterface
{
    private string $sessionId = '';
    private bool $sessionStarted = false;
    private DataHandlerInterface $dataHandler;
    
    private AttributeBag $attributeBag;
    private FlashBag $flashBag;
    private MetadataBag $metadataBag;
    
    private array $payload = [];

    public function __construct(
        private readonly SessionDriverInterface $driver,
        ?DataHandlerInterface $dataHandler = null,
        private readonly string $sessionName = 'monkeyslegion_session'
    ) {
        $this->dataHandler = $dataHandler ?: new NativeSerializer();
        $this->attributeBag = new AttributeBag();
        $this->flashBag = new FlashBag();
        $this->metadataBag = new MetadataBag();
    }

    public string $id {
        get => $this->sessionId ?: '';
        set(string $value) {
            $this->sessionId = $value;
        }
    }

    public bool $isStarted {
        get => $this->sessionStarted;
    }

    public AttributeBag $attributes {
        get {
            $this->start();
            return $this->attributeBag;
        }
    }

    public FlashBag $flashes {
        get {
            $this->start();
            return $this->flashBag;
        }
    }

    public MetadataBag $metadata {
        get {
            $this->start();
            return $this->metadataBag;
        }
    }

    private ?string $clientIp = null;
    private ?string $clientUa = null;

    public function setRequestInfo(?string $ip, ?string $userAgent): void
    {
        $this->clientIp = $ip;
        $this->clientUa = $userAgent;
    }

    public function start(): bool
    {
        if ($this->sessionStarted) {
            return true;
        }

        if (empty($this->sessionId)) {
            $this->sessionId = $this->generateId();
        }

        // 1. Lock the session
        if (!$this->driver->lock($this->sessionId)) {
            throw SessionException::driverFailed('lock', 'Could not acquire session lock');
        }

        // 2. Read and initialize — release lock on any failure
        try {
            $data = $this->driver->read($this->sessionId);

            if ($data !== null) {
                try {
                    $this->payload = $this->dataHandler->restore($data['payload'] ?? '');
                } catch (\Throwable) {
                    $this->payload = [];
                }
            } else {
                $this->payload = [];
            }

            // 3. Initialize bags
            $attrData  = &$this->getBagData($this->attributeBag->getStorageKey());
            $flashData = &$this->getBagData($this->flashBag->getStorageKey());
            $metaData  = &$this->getBagData($this->metadataBag->getStorageKey());

            $this->attributeBag->initialize($attrData);
            $this->flashBag->initialize($flashData);
            $this->metadataBag->initialize($metaData);

            // Populate context info if provided
            if ($this->clientIp !== null) {
                $this->metadataBag->set('ip_address', $this->clientIp);
            }
            if ($this->clientUa !== null) {
                $this->metadataBag->set('user_agent', $this->clientUa);
            }

            // Stamp usage
            $this->metadataBag->stampNew();

            // 4. Ensure CSRF (optional but good default)
            if (!$this->attributeBag->has('_token')) {
                $this->attributeBag->set('_token', bin2hex(random_bytes(40)));
            }

            $this->sessionStarted = true;
            return true;
        } catch (\Throwable $e) {
            $this->driver->unlock($this->sessionId);
            throw $e;
        }
    }

    private function &getBagData(string $key): array
    {
        if (!isset($this->payload[$key]) || !is_array($this->payload[$key])) {
            $this->payload[$key] = [];
        }
        return $this->payload[$key];
    }

    public function save(): bool
    {
        if (!$this->sessionStarted || empty($this->sessionId)) {
            return false;
        }

        $this->flashBag->clearOldData();

        $serialized = $this->dataHandler->prepare($this->payload);

        $metadata = [
            'ip_address' => $this->metadataBag->get('ip_address'),
            'user_agent' => $this->metadataBag->get('user_agent'),
            'user_id'    => $this->metadataBag->get('user_id'),
        ];

        try {
            return $this->driver->write($this->sessionId, $serialized, $metadata);
        } finally {
            $this->driver->unlock($this->sessionId);
            $this->sessionStarted = false;
        }
    }

    public function regenerate(bool $destroy = false): bool
    {
        if (!$this->sessionStarted) {
            $this->start();
        }

        $oldId = $this->sessionId;
        $this->sessionId = $this->generateId();

        // Always release old lock, then optionally destroy data
        try {
            if ($destroy) {
                $this->driver->destroy($oldId);
            }
        } finally {
            $this->driver->unlock($oldId);
        }

        if (!$this->driver->lock($this->sessionId)) {
            throw SessionException::driverFailed('lock', 'Could not acquire lock for regenerated session');
        }

        $this->metadataBag->set('created_at', time());

        return true;
    }

    public function invalidate(): bool
    {
        // Ensure session is started so bags are populated from storage
        if (!$this->sessionStarted) {
            $this->start();
        }

        $this->attributeBag->clear();
        $this->flashBag->clear();
        $this->metadataBag->clear();
        $this->payload = [];

        return $this->regenerate(true);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(20));
    }

    // ── Interface Proxy Methods ─────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->attributes->has($key);
    }

    public function remove(string $key): void
    {
        $this->attributes->forget($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->attributes->pull($key, $default);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->flashes->set($key, $value);
    }

    public function reflash(): void
    {
        $this->flashes->reflash();
    }

    public function keep(string ...$keys): void
    {
        $this->flashes->keep(...$keys);
    }

    public function now(string $key, mixed $value): void
    {
        $this->flashes->now($key, $value);
    }

    public function all(): array
    {
        return $this->attributes->all();
    }

    public function token(): string
    {
        return (string) $this->attributes->get('_token', '');
    }

    public function regenerateToken(): void
    {
        $this->attributes->set('_token', bin2hex(random_bytes(40)));
    }

    // ── Legacy IDE Proxy Methods (Fixes PHP2414) ────────────────
    // Because some IDEs/parsers cache the older non-hook version 
    // of SessionInterface, adding these fulfills the phantom AST.

    public function destroy(): bool
    {
        return $this->invalidate();
    }

    public function getId(): ?string
    {
        return $this->sessionId ?: null;
    }

    public function isStarted(): bool
    {
        return $this->sessionStarted;
    }

    public function setId(string $id): void
    {
        $this->sessionId = $id;
    }
}
