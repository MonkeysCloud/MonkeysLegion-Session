<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Exceptions;

class SessionLockException extends SessionException
{
    /**
     * Create a new exception for when lock acquisition times out.
     *
     * @param string $sessionId
     * @param int $timeout
     * @return static
     */
    public static function timeout(string $sessionId, int $timeout): static
    {
        return new static(
            "Failed to acquire lock for session '{$sessionId}' within {$timeout} seconds."
        );
    }

    /**
     * Create a new exception for when lock acquisition fails.
     *
     * @param string $sessionId
     * @param string $reason
     * @return static
     */
    public static function acquisitionFailed(string $sessionId, string $reason = ''): static
    {
        $msg = "Failed to acquire lock for session '{$sessionId}'.";
        if ($reason) {
            $msg .= ' Reason: ' . $reason;
        }
        return new static($msg);
    }

    /**
     * Create a new exception for when lock release fails.
     *
     * @param string $sessionId
     * @param string $reason
     * @return static
     */
    public static function releaseFailed(string $sessionId, string $reason = ''): static
    {
        $msg = "Failed to release lock for session '{$sessionId}'.";
        if ($reason) {
            $msg .= ' Reason: ' . $reason;
        }
        return new static($msg);
    }

    /**
     * Create a new exception for when attempting to lock an already locked session.
     *
     * @param string $sessionId
     * @return static
     */
    public static function alreadyLocked(string $sessionId): static
    {
        return new static("Session '{$sessionId}' is already locked.");
    }

    /**
     * Create a new exception for when attempting to unlock a non-locked session.
     *
     * @param string $sessionId
     * @return static
     */
    public static function notLocked(string $sessionId): static
    {
        return new static("Session '{$sessionId}' is not locked.");
    }
}
