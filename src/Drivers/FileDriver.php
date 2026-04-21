<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Drivers;

use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Exceptions\SessionException;
use MonkeysLegion\Session\Exceptions\SessionLockException;

/**
 * File-based session driver.
 */
class FileDriver implements SessionDriverInterface
{
    private string $path;
    private int $ttl;
    
    /** @var array<string, bool> */
    private array $locks = [];
    
    /** @var array<string, resource> */
    private array $lockHandles = [];

    /**
     * @param string $path The directory path where session files are stored
     * @param int $ttl Session time-to-live in seconds (default: 7200)
     */
    public function __construct(string $path, int $ttl = 7200)
    {
        $this->path = rtrim($path, '/\\');
        $this->ttl = $ttl;

        if (!is_dir($this->path)) {
            if (!@mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                throw SessionException::driverFailed('open', "Failed to create session directory: {$this->path}");
            }
        }

        if (!is_writable($this->path)) {
            throw SessionException::driverFailed('open', "Session directory is not writable: {$this->path}");
        }
    }

    public function __destruct()
    {
        try {
            if (!empty($this->locks)) {
                $this->close();
            }
        } catch (\Throwable) {
            // Silence errors in destructor
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        foreach (array_keys($this->locks) as $id) {
            $this->unlock((string)$id);
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

        /** @var mixed $session */
        $session = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($session)) {
            return null;
        }

        /** @var array<string, mixed> $session */
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

        /** @var array<string, mixed> $session */
        $session = [];
        if (file_exists($filepath)) {
            $content = @file_get_contents($filepath);
            if ($content !== false) {
                /** @var mixed $decoded */
                $decoded = json_decode($content, true);
                $session = is_array($decoded) ? $decoded : [];
            }
        } else {
             $session['created_at'] = time();
             $session['session_id'] = $id;
        }

        $session['payload'] = $payload;
        $session['last_activity'] = time();
        
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

        if (isset($this->locks[$id])) {
            $this->unlock($id);
        }

        if (file_exists($filepath)) {
            @unlink($filepath);
        }

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
            $pattern = $this->path . '/sess_*';
            $files = glob($pattern);

            if ($files === false) {
                return false;
            }

            foreach ($files as $file) {
                if (str_ends_with($file, '.lock') || str_ends_with($file, '.tmp')) {
                    continue;
                }

                $content = @file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                /** @var mixed $session */
                $session = json_decode($content, true);
                if (!is_array($session)) {
                    continue;
                }

                /** @var mixed $lastActivity */
                $lastActivity = $session['last_activity'] ?? 0;
                if (is_numeric($lastActivity) && (int)$lastActivity < $threshold) {
                    @unlink($file);
                    $lockFile = $file . '.lock';
                    if (file_exists($lockFile)) {
                        @unlink($lockFile);
                    }
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id, int $timeout = 30): bool
    {
        if (isset($this->locks[$id])) {
            throw SessionLockException::alreadyLocked($id);
        }

        $lockFilepath = $this->getLockFilePath($id);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            /** @var resource|false $handle */
            $handle = @fopen($lockFilepath, 'c+');

            if ($handle === false) {
                throw SessionLockException::acquisitionFailed($id, 'Failed to open lock file');
            }

            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->locks[$id] = true;
                $this->lockHandles[$id] = $handle;

                ftruncate($handle, 0);
                fwrite($handle, (string)json_encode([
                    'pid' => getmypid(),
                    'time' => time(),
                ]));
                fflush($handle);

                return true;
            }

            fclose($handle);
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
            return true;
        }

        if (isset($this->lockHandles[$id])) {
            $handle = $this->lockHandles[$id];
            flock($handle, LOCK_UN);
            fclose($handle);
            unset($this->lockHandles[$id]);
        }

        unset($this->locks[$id]);

        $lockFilepath = $this->getLockFilePath($id);
        if (file_exists($lockFilepath)) {
            @unlink($lockFilepath);
        }

        return true;
    }

    private function getFilePath(string $id): string
    {
        return $this->path . '/sess_' . $id;
    }

    private function getLockFilePath(string $id): string
    {
        return $this->path . '/sess_' . $id . '.lock';
    }

    /**
     * @param string $filepath
     * @param array<string, mixed> $session
     * @return bool
     */
    private function saveSessionFile(string $filepath, array $session): bool
    {
        $json = json_encode($session, JSON_THROW_ON_ERROR);

        $tempFile = $filepath . '.tmp';
        $bytes = @file_put_contents($tempFile, $json, LOCK_EX);

        if ($bytes === false) {
            throw SessionException::driverFailed('write', "Failed to write to temporary file: {$tempFile}");
        }

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
            return false;
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
}
