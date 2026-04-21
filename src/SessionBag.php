<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

/**
 * Container for session attributes and flash data.
 */
class SessionBag
{
    /** @var array<string, mixed> */
    private array $attributes = [];
    
    /** @var array<string, mixed> */
    private array $flash = [];
    
    /** @var array<string, mixed> */
    private array $oldFlash = [];

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $flash
     */
    public function __construct(array $attributes = [], array $flash = [])
    {
        $this->attributes = $attributes;
        $this->oldFlash = $flash;
    }

    /**
     * Get all session attributes.
     * 
     * @return array<string, mixed>
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
     * 
     * @param string|array<string, mixed> $key
     * @param mixed $value
     */
    public function put(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->arraySet($this->attributes, (string)$k, $v);
            }
        } else {
            $this->arraySet($this->attributes, $key, $value);
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
        $this->oldFlash[$key] = $value;
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
     * 
     * @param array<int, string> $keys
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
     * 
     * @param array<string, mixed> $keys
     */
    protected function mergeNewFlash(array $keys): void
    {
        $this->flash = array_merge($this->flash, $keys);
    }

    /**
     * Get the current flash data (to be saved for next request).
     * 
     * @return array<string, mixed>
     */
    public function getNewFlash(): array
    {
        return $this->flash;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function arrayGet(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $current = $array;
        foreach (explode('.', $key) as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function arraySet(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);

        $current = &$array;
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if ($segment === null) break;

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            /** @var array<string, mixed> $subArray */
            $subArray = &$current[$segment];
            $current = &$subArray;
        }

        $lastSegment = array_shift($keys);
        if ($lastSegment !== null) {
            $current[$lastSegment] = $value;
        }

        return $array;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function arrayForget(array &$array, string $key): void
    {
        $keys = explode('.', $key);

        $current = &$array;
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if ($segment === null) break;

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }

        $lastSegment = array_shift($keys);
        if ($lastSegment !== null) {
            unset($current[$lastSegment]);
        }
    }
}
