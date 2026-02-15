<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Contracts;

interface SessionDriverInterface
{
    /**
     * Initialize the storage resource.
     *
     * @param string $path The path where sessions are stored
     * @param string $name The session name
     * @return bool True on success
     */
    public function open(string $path, string $name): bool;

    /**
     * Close the storage resource.
     *
     * @return bool True on success
     */
    public function close(): bool;

    /**
     * Retrieve serialized session data by ID.
     *
     * @param string $id The session ID
     * @return array|null The session data (payload + metadata), or null if not found
     */
    public function read(string $id): ?array;

    /**
     * Save serialized session data.
     *
     * @param string $id The session ID
     * @param string $payload The serialized session data
     * @param array $metadata Additional metadata (flash, created_at, etc.)
     * @return bool True on success
     */
    public function write(string $id, string $payload, array $metadata): bool;

    /**
     * Delete the session data from storage.
     *
     * @param string $id The session ID
     * @return bool True on success
     */
    public function destroy(string $id): bool;

    /**
     * Garbage Collection: Delete sessions older than maxLifetime.
     *
     * @param int $maxLifetime Session lifetime in seconds
     * @return int|false Number of sessions deleted, or false on failure
     */
    public function gc(int $maxLifetime): int|false;

    /**
     * Acquire an exclusive lock on the session ID.
     *
     * @param string $id The session ID
     * @param int $timeout Lock timeout in seconds
     * @return bool True if lock acquired, false on timeout
     */
    public function lock(string $id, int $timeout = 30): bool;

    /**
     * Release the lock so other requests can proceed.
     *
     * @param string $id The session ID
     * @return bool True on success
     */
    public function unlock(string $id): bool;
}
