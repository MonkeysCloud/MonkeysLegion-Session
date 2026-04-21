<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Contracts;

interface SessionInterface
{
    /**
     * The unique identifier for this session.
     */
    public string $id { get; set; }

    /**
     * Whether the session has been started successfully.
     */
    public bool $isStarted { get; }

    /**
     * Start or resume the session.
     */
    public function start(): bool;

    /**
     * Regenerate the session ID (crucial for login to prevent fixation).
     */
    public function regenerate(bool $destroy = false): bool;

    /**
     * Save the session data to storage.
     */
    public function save(): bool;

    /**
     * Invalidate (destroy) the session completely.
     */
    public function invalidate(): bool;

    /**
     * Retrieve a value from the session (supports dot notation).
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the session (supports dot notation).
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session.
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session.
     */
    public function forget(string $key): void;

    /**
     * Get a value and immediately delete it.
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Store flash data for the next request only.
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Retrieve flash data.
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Reflash all flash data for another request.
     */
    public function reflash(): void;

    /**
     * Keep specific flash data keys for another request.
     */
    public function keep(string ...$keys): void;

    /**
     * Store flash data that will be available immediately and expire at end of request.
     */
    public function now(string $key, mixed $value): void;

    /**
     * Set the user's IP address for validation.
     */
    public function setIpAddress(?string $ip): void;

    /**
     * Set the user's browser string for validation.
     */
    public function setUserAgent(?string $ua): void;

    /**
     * Associate a User ID with the session.
     */
    public function setUserId(string|int|null $id): void;

    /**
     * Get all session data from the attribute bag.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Get the CSRF token.
     */
    public function token(): string;

    /**
     * Regenerate the CSRF token value.
     */
    public function regenerateToken(): void;
}
