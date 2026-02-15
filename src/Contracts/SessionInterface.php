<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Contracts;

interface SessionInterface
{
    /**
     * Start or resume the session.
     *
     * @return bool True if session started successfully
     */
    public function start(): bool;

    /**
     * Get the current session ID.
     *
     * @return string|null
     */
    public function getId(): ?string;

    /**
     * Set the session ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void;

    /**
     * Retrieve a value from the session (supports dot notation).
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the session (supports dot notation).
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Store flash data for the next request only.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Get a value and immediately delete it.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Regenerate the session ID (crucial for login to prevent fixation).
     *
     * @param bool $deleteOldSession Whether to delete the old session data
     * @return bool
     */
    public function regenerate(bool $deleteOldSession = false): bool;

    /**
     * Destroy the session and remove all data.
     *
     * @return bool
     */
    public function destroy(): bool;

    /**
     * Save the session data to storage.
     *
     * @return bool
     */
    public function save(): bool;

    /**
     * Check if the session has been started.
     *
     * @return bool
     */
    public function isStarted(): bool;

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all(): array;
}
