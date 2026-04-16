<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Bags;

use MonkeysLegion\Session\Contracts\SessionBagInterface;

class FlashBag implements SessionBagInterface
{
    /** @var array<string, mixed> */
    private array $flashes = [];
    
    /** @var array<string, string> */
    private array $newKeys = [];
    
    /** @var array<string, string> */
    private array $oldKeys = [];

    public string $name {
        get => 'flash';
    }

    public function initialize(array &$array): void
    {
        $this->flashes = &$array;

        // On initialize, we consider all existing keys as "old" 
        // since they came from the previous request.
        $this->oldKeys = array_keys($array);
        $this->newKeys = [];
    }

    public function getStorageKey(): string
    {
        return '_flash';
    }

    public function clear(): array
    {
        $return = $this->flashes;
        $this->flashes = [];
        $this->newKeys = [];
        $this->oldKeys = [];
        return $return;
    }

    /**
     * Flash a key / value pair to the session.
     */
    public function set(string $key, mixed $value): void
    {
        $this->flashes[$key] = $value;
        
        $this->newKeys[] = $key;
        
        // Remove from old keys if it was there
        $this->oldKeys = array_values(array_diff($this->oldKeys, [$key]));
    }

    /**
     * Get a flash item.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->flashes[$key] ?? $default;
    }

    /**
     * Store flash data that will be available immediately and expire at end of request.
     */
    public function now(string $key, mixed $value): void
    {
        $this->flashes[$key] = $value;
        $this->oldKeys[] = $key;
    }

    /**
     * Reflash all of the session flash data for another request.
     */
    public function reflash(): void
    {
        $this->newKeys = array_merge($this->newKeys, $this->oldKeys);
        $this->oldKeys = [];
    }

    /**
     * Keep specific flash data keys for another request.
     */
    public function keep(string ...$keys): void
    {
        $this->newKeys = array_unique(array_merge($this->newKeys, $keys));
        $this->oldKeys = array_values(array_diff($this->oldKeys, $keys));
    }

    /**
     * Determine if a flash key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->flashes);
    }
    
    /**
     * Clear old flash data. Called by the manager before saving the session.
     */
    public function clearOldData(): void
    {
        foreach ($this->oldKeys as $key) {
            unset($this->flashes[$key]);
        }
        
        // Next request, the new keys become old keys
        // (Handled implicitly when initialize() is called on next boot)
    }
}
