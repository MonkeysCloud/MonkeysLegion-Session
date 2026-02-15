<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Tests\Drivers;

use MonkeysLegion\Session\Drivers\DatabaseDriver;

/**
 * Test shim for SQLite locking.
 * Note: Inherits read/write from DatabaseDriver, so no changes needed there 
 * unless DatabaseDriver constructor/dependencies changed (they didn't).
 */
class TestDatabaseDriver extends DatabaseDriver
{
    /** @var array<string, resource> */
    private array $lockHandles = [];

    public function lock(string $id, int $timeout = 30): bool
    {
        $filename = sys_get_temp_dir() . '/sess_lock_' . md5($id);
        
        // Suppress warning if file cannot be opened (simulating failure)
        $handle = @fopen($filename, 'w+');
        
        if ($handle === false) {
            return false;
        }

        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            // Try to acquire exclusive lock (non-blocking)
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->lockHandles[$id] = $handle;
                return true;
            }

            // Wait a bit before retrying
            usleep(10000); // 10ms
        }

        fclose($handle);
        return false;
    }

    public function unlock(string $id): bool
    {
        if (!isset($this->lockHandles[$id])) {
            return true;
        }

        $handle = $this->lockHandles[$id];
        flock($handle, LOCK_UN);
        fclose($handle);
        
        unset($this->lockHandles[$id]);
        
        // Optional: Attempt to remove the lock file, though race conditions make this tricky
        // so we might just leave it.
        $filename = sys_get_temp_dir() . '/sess_lock_' . md5($id);
        if (file_exists($filename)) {
            @unlink($filename);
        }

        return true;
    }
}
