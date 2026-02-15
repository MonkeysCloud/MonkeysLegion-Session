<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Drivers;

use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Exceptions\SessionException;
use MonkeysLegion\Session\Exceptions\SessionLockException;

class FileDriver implements SessionDriverInterface
{
    private string $path;
    private int $ttl;
    private array $locks = [];
    private array $lockHandles = [];

    /**
     * Create a new File driver instance.
     *
     * @param string $path The directory path where session files are stored
     * @param int $ttl Session time-to-live in seconds (default: 7200)
     */
    public function __construct(string $path, int $ttl = 7200)
    {
        $this->path = rtrim($path, '/\\');
        $this->ttl = $ttl;

        // Ensure the directory exists
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                throw SessionException::driverFailed('open', "Failed to create session directory: {$this->path}");
            }
        }

        // Ensure the directory is writable
        if (!is_writable($this->path)) {
            throw SessionException::driverFailed('open', "Session directory is not writable: {$this->path}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        // Directory is already validated in constructor
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        // Release all locks before closing
        foreach (array_keys($this->locks) as $id) {
            $this->unlock($id);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): ?array
    {
        $filepath = $this->getFilePath($id);

        if (!file_exists($filepath)) {
            return null;
        }

        $content = @file_get_contents($filepath);

        if ($content === false) {
            throw SessionException::driverFailed('read', "Failed to read session file: {$filepath}");
        }

        // Parse the session file (format: JSON with metadata)
        $session = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Treat corrupted file as missing session
            return null;
        }

        // Update last activity time
        $session['last_activity'] = time();
        $this->saveSessionFile($filepath, $session);

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $payload, array $metadata): bool
    {
        $filepath = $this->getFilePath($id);

        // Read existing session to preserve other metadata if needed
        $session = [];
        if (file_exists($filepath)) {
            $content = @file_get_contents($filepath);
            if ($content !== false) {
                $session = json_decode($content, true) ?? [];
            }
        } else {
             $session['created_at'] = time();
             $session['session_id'] = $id;
        }

        // Merge new data
        $session['payload'] = $payload;
        $session['last_activity'] = time();
        
        // Merge allowed metadata
        $session = array_merge($session, $metadata);

        return $this->saveSessionFile($filepath, $session);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        $filepath = $this->getFilePath($id);
        $lockFilepath = $this->getLockFilePath($id);

        // Unlock first if locked
        if (isset($this->locks[$id])) {
            $this->unlock($id);
        }

        // Delete session file
        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        // Delete lock file
        if (file_exists($lockFilepath)) {
            @unlink($lockFilepath);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): int|false
    {
        $threshold = time() - $maxLifetime;
        $count = 0;

        try {
            $files = glob($this->path . '/sess_*');

            if ($files === false) {
                return false;
            }

            foreach ($files as $file) {
                // Skip lock files
                if (str_ends_with($file, '.lock')) {
                    continue;
                }

                $content = @file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                $session = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                // Check if session has expired based on last_activity
                if (isset($session['last_activity']) && $session['last_activity'] < $threshold) {
                    @unlink($file);
                    // Also remove lock file if exists
                    $lockFile = $file . '.lock';
                    if (file_exists($lockFile)) {
                        @unlink($lockFile);
                    }
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id, int $timeout = 30): bool
    {
        // If already locked by this instance, don't re-lock
        if (isset($this->locks[$id])) {
            throw SessionLockException::alreadyLocked($id);
        }

        $lockFilepath = $this->getLockFilePath($id);
        $deadline = microtime(true) + $timeout;

        // Try to acquire lock with retry
        while (microtime(true) < $deadline) {
            // Open lock file (create if doesn't exist)
            $handle = @fopen($lockFilepath, 'c+');

            if ($handle === false) {
                throw SessionLockException::acquisitionFailed($id, 'Failed to open lock file');
            }

            // Try to acquire exclusive lock (non-blocking)
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                // Lock acquired successfully
                $this->locks[$id] = true;
                $this->lockHandles[$id] = $handle;

                // Write lock metadata
                ftruncate($handle, 0);
                fwrite($handle, json_encode([
                    'pid' => getmypid(),
                    'time' => time(),
                ]));
                fflush($handle);

                return true;
            }

            // Lock not acquired, close handle and retry
            fclose($handle);

            // Exponential backoff
            usleep(50000); // 50ms
        }

        throw SessionLockException::timeout($id, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string $id): bool
    {
        if (!isset($this->locks[$id])) {
            // Silently return true if not locked by this instance
            return true;
        }

        if (isset($this->lockHandles[$id])) {
            $handle = $this->lockHandles[$id];

            // Release the lock
            flock($handle, LOCK_UN);
            fclose($handle);

            unset($this->lockHandles[$id]);
        }

        unset($this->locks[$id]);

        // Optionally remove the lock file
        $lockFilepath = $this->getLockFilePath($id);
        if (file_exists($lockFilepath)) {
            @unlink($lockFilepath);
        }

        return true;
    }

    /**
     * Get the file path for a session ID.
     *
     * @param string $id
     * @return string
     */
    private function getFilePath(string $id): string
    {
        return $this->path . '/sess_' . $id;
    }

    /**
     * Get the lock file path for a session ID.
     *
     * @param string $id
     * @return string
     */
    private function getLockFilePath(string $id): string
    {
        return $this->path . '/sess_' . $id . '.lock';
    }

    /**
     * Save session data to file with proper error handling.
     *
     * @param string $filepath
     * @param array $session
     * @return bool
     */
    private function saveSessionFile(string $filepath, array $session): bool
    {
        $json = json_encode($session, JSON_THROW_ON_ERROR);

        if ($json === false) {
            throw SessionException::serializationFailed(json_last_error_msg());
        }

        // Write atomically using temporary file
        $tempFile = $filepath . '.tmp';
        $bytes = @file_put_contents($tempFile, $json, LOCK_EX);

        if ($bytes === false) {
            throw SessionException::driverFailed('write', "Failed to write to temporary file: {$tempFile}");
        }

        // Atomic rename
        if (!@rename($tempFile, $filepath)) {
            @unlink($tempFile);
            throw SessionException::driverFailed('write', "Failed to rename temporary file to: {$filepath}");
        }

        return true;
    }

    /**
     * Create a new session file with metadata.
     *
     * @param string $id
     * @return bool
     */
    public function create(string $id): bool
    {
        $filepath = $this->getFilePath($id);

        if (file_exists($filepath)) {
            return false; // Session already exists
        }

        $session = [
            'session_id' => $id,
            'payload' => '',
            'created_at' => time(),
            'last_activity' => time(),
            'expiration' => time() + $this->ttl,
        ];

        return $this->saveSessionFile($filepath, $session);
    }

    /**
     * Set the TTL for sessions.
     *
     * @param int $ttl Time-to-live in seconds
     * @return void
     */
    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * Get the current TTL setting.
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}
