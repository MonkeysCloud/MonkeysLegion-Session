<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Bags;

use MonkeysLegion\Session\Contracts\SessionBagInterface;

class MetadataBag implements SessionBagInterface
{
    /** @var array<string, mixed> */
    private array $meta = [];

    private int $updateThreshold = 0; // seconds before updating last_used_at to save DB writes
    private int $lastUsedAtStamp = 0;

    public string $name {
        get => 'metadata';
    }

    public function initialize(array &$array): void
    {
        $this->meta = &$array;

        if (!isset($this->meta['created_at'])) {
            $this->meta['created_at'] = time();
        }

        if (!isset($this->meta['last_used_at'])) {
            $this->meta['last_used_at'] = time();
        }
        
        $this->lastUsedAtStamp = (int) $this->meta['last_used_at'];
    }

    public function getStorageKey(): string
    {
        return '_meta';
    }

    public function clear(): array
    {
        $return = $this->meta;
        $this->meta = [];
        return $return;
    }

    /**
     * Gets the session creation timestamp.
     */
    public function getCreatedAt(): int
    {
        return (int) ($this->meta['created_at'] ?? time());
    }

    /**
     * Gets the last used timestamp.
     */
    public function getLastUsedAt(): int
    {
        return (int) ($this->meta['last_used_at'] ?? time());
    }

    /**
     * Set the threshold before updating the last_used_at timestamp.
     */
    public function setUpdateThreshold(int $seconds): void
    {
        $this->updateThreshold = $seconds;
    }

    /**
     * Registers a "usage" tick. Will update last_used_at if passed threshold.
     */
    public function stampNew(): void
    {
        $now = time();
        if ($now - $this->lastUsedAtStamp >= $this->updateThreshold) {
            $this->meta['last_used_at'] = $now;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }
}
