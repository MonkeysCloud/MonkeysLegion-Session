<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

class SessionBag
{
    private array $attributes = [];
    private array $flash = [];
    private array $oldFlash = [];

    public function __construct(array $attributes = [], array $flash = [])
    {
        $this->attributes = $attributes;
        $this->oldFlash = $flash;
    }

    /**
     * Get all session attributes.
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Get an item from the session attributes.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->arrayGet($this->attributes, $key, $default);
    }

    /**
     * Put a key / value pair or array of key / value pairs in the session.
     */
    public function put(string|array $key, mixed $value = null): void
    {
        if (!is_array($key)) {
            $key = [$key => $value];
        }

        foreach ($key as $k => $v) {
            $this->arraySet($this->attributes, $k, $v);
        }
    }

    /**
     * Determine if the session contains a given item.
     */
    public function has(string $key): bool
    {
        return $this->arrayGet($this->attributes, $key) !== null;
    }

    /**
     * Remove an item from the session.
     */
    public function forget(string $key): void
    {
        $this->arrayForget($this->attributes, $key);
    }

    /**
     * Remove all of the items from the session.
     */
    public function flush(): void
    {
        $this->attributes = [];
    }

    /**
     * Flash a key / value pair to the session.
     */
    public function flash(string $key, mixed $value = true): void
    {
        $this->flash[$key] = $value;
        $this->oldFlash[$key] = $value; // Available immediately for this request too if needed
    }

    /**
     * Reflash all of the session flash data.
     */
    public function reflash(): void
    {
        $this->mergeNewFlash($this->oldFlash);
    }

    /**
     * Reflash a subset of the current flash data.
     */
    public function keep(array $keys = []): void
    {
        $this->mergeNewFlash(array_intersect_key($this->oldFlash, array_flip($keys)));
    }
    
    /**
     * Get a flash item.
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->oldFlash[$key] ?? $default;
    }
    
    /**
     * Merge new flash keys into the new flash array.
     */
    protected function mergeNewFlash(array $keys): void
    {
        $this->flash = array_merge($this->flash, $keys);
    }

    /**
     * Get the current flash data (to be saved for next request).
     */
    public function getNewFlash(): array
    {
        return $this->flash;
    }

    // --- Dot Notation Helpers (Simplified) ---

    // Copied/Adapted from Illuminate/Support/Arr or similar
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

    private function arraySet(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    private function arrayForget(array &$array, string $key): void
    {
        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                return;
            }

            $array = &$array[$key];
        }

        unset($array[array_shift($keys)]);
    }
}
