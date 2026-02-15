<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Exceptions;

use RuntimeException;

class SessionException extends RuntimeException
{
    /**
     * Create a new session exception for when session has already started.
     *
     * @return static
     */
    public static function alreadyStarted(): static
    {
        return new static('Session has already been started.');
    }

    /**
     * Create a new session exception for when session is not started.
     *
     * @return static
     */
    public static function notStarted(): static
    {
        return new static('Session has not been started yet.');
    }

    /**
     * Create a new session exception for invalid session ID.
     *
     * @param string $id
     * @return static
     */
    public static function invalidId(string $id): static
    {
        return new static("Invalid session ID: {$id}");
    }

    /**
     * Create a new session exception for serialization failure.
     *
     * @param string $message
     * @return static
     */
    public static function serializationFailed(string $message = ''): static
    {
        $msg = 'Failed to serialize session data.';
        if ($message) {
            $msg .= ' ' . $message;
        }
        return new static($msg);
    }

    /**
     * Create a new session exception for deserialization failure.
     *
     * @param string $message
     * @return static
     */
    public static function deserializationFailed(string $message = ''): static
    {
        $msg = 'Failed to deserialize session data.';
        if ($message) {
            $msg .= ' ' . $message;
        }
        return new static($msg);
    }

    /**
     * Create a new session exception for driver operation failure.
     *
     * @param string $operation
     * @param string $message
     * @return static
     */
    public static function driverFailed(string $operation, string $message = ''): static
    {
        $msg = "Session driver operation '{$operation}' failed.";
        if ($message) {
            $msg .= ' ' . $message;
        }
        return new static($msg);
    }

    /**
     * Create a new session exception for security validation failure.
     *
     * @param string $reason
     * @return static
     */
    public static function securityValidationFailed(string $reason): static
    {
        return new static("Session security validation failed: {$reason}");
    }

    /**
     * Create a new session exception for expired session.
     *
     * @return static
     */
    public static function expired(): static
    {
        return new static('Session has expired.');
    }
}
