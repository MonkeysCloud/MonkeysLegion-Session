<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Bags;

use MonkeysLegion\Session\Contracts\SessionBagInterface;

class AttributeBag implements SessionBagInterface
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public string $name {
        get => 'attributes';
    }

    public function initialize(array &$array): void
    {
        $this->attributes = &$array;
    }

    public function getStorageKey(): string
    {
        return '_attributes';
    }

    public function clear(): array
    {
        $return = $this->attributes;
        $this->attributes = [];
        return $return;
    }

    /**
     * Get all attributes.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute with dot notation support.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->arrayGet($this->attributes, $key, $default);
    }

    /**
     * Set an attribute with dot notation support.
     */
    public function set(string $key, mixed $value): void
    {
        $this->arraySet($this->attributes, $key, $value);
    }

    /**
     * Check if an attribute exists.
     */
    public function has(string $key): bool
    {
        return $this->arrayGet($this->attributes, $key) !== null;
    }

    /**
     * Remove an attribute.
     */
    public function forget(string $key): void
    {
        $this->arrayForget($this->attributes, $key);
    }

    /**
     * Get a value and immediately delete it.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    // ── Dot Notation Helpers ─────────────────────────────────────

    private function arrayGet(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $array[$key] ?? $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    private function arraySet(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);

        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array = &$array[$segment];
        }

        $array[array_shift($keys)] = $value;
    }

    private function arrayForget(array &$array, string $key): void
    {
        $keys = explode('.', $key);

        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                return;
            }

            $array = &$array[$segment];
        }

        unset($array[array_shift($keys)]);
    }
}
