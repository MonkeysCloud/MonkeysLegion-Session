<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Contracts;

interface DataHandlerInterface
{
    /**
     * Prepare data for storage (e.g., serialize and/or encrypt).
     *
     * @param mixed $data The raw session data
     * @return string The data ready for storage
     */
    public function prepare(mixed $data): string;

    /**
     * Restore data from storage (e.g., decrypt and/or unserialize).
     *
     * @param string $data The stored data string
     * @return mixed The restored raw session data
     */
    public function restore(string $data): mixed;
}
