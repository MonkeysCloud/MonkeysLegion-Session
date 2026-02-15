<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Drivers;

use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;

class DatabaseDriver implements SessionDriverInterface
{
    private QueryBuilder $queryBuilder;
    private string $table;

    private const LOCK_KEY = 'ml_session_';

    public function __construct(
        private ConnectionInterface $connection,
        private array $config
    ) {
        $this->queryBuilder = new QueryBuilder($this->connection);
        $this->table = $config['table'] ?? 'sessions';
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        // No initialization needed for database connection
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        // No cleanup needed for database connection
        return true;
    }

    /**
     * {@inheritdoc}
     */
    /**
     * {@inheritdoc}
     */
    public function read(string $id): ?array
    {
        $session = $this->queryBuilder
            ->from($this->table)
            ->where('session_id', '=', $id)
            ->first();

        if (!$session) {
            return null;
        }

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $payload, array $metadata): bool
    {
        // Setup default data array
        $data = [
            'payload' => $payload,
            'last_activity' => time()
        ];
        
        // Merge allowed metadata columns if they exist in metadata array
        // We do this to prevent arbitrary metadata from crashing SQL update if column doesn't exist
        $allowedColumns = ['flash_data', 'user_id', 'ip_address', 'user_agent'];
        foreach ($allowedColumns as $col) {
            if (array_key_exists($col, $metadata)) {
                $data[$col] = $metadata[$col];
            }
        }

        // Check if session exists (UPSERT would be better but keeping it simple for now)
        $exists = $this->queryBuilder
            ->from($this->table)
            ->where('session_id', '=', $id)
            ->count() > 0;

        if ($exists) {
            return $this->queryBuilder
                ->update($this->table, $data)
                ->where('session_id', '=', $id)
                ->execute() > 0;
        } else {
            // New session
            $data['session_id'] = $id;
            $data['created_at'] = time();
            
            $this->queryBuilder->insert($this->table, $data);
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        return $this->queryBuilder
            ->delete($this->table)
            ->where('session_id', '=', $id)
            ->execute() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): int|false
    {
        $threshold = time() - $maxLifetime;

        $count = (new QueryBuilder($this->connection))
            ->from($this->table)
            ->where('last_activity', '<', $threshold)
            ->count();

        if ($count === 0) {
            return 0;
        }

        $deleted = (new QueryBuilder($this->connection))
            ->delete($this->table)
            ->where('last_activity', '<', $threshold)
            ->execute();

        return $deleted > 0 ? $count : false;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id, int $timeout = 30): bool
    {
        // We use a prefix to avoid collisions with other app locks
        $lockName = self::LOCK_KEY . $id;

        // GET_LOCK returns 1 if successful, 0 if it timed out, NULL on error
        $result = $this->queryBuilder->raw("SELECT GET_LOCK(?, ?)", [$lockName, $timeout]);

        //TODO : VERIFY THE RESULT OF RELEASE_LOCK, IT'S ARRAY NOT BOOLEAN
        return (int)$result === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string $id): bool
    {
        $lockName = self::LOCK_KEY . $id;

        // RELEASE_LOCK returns 1 if released, 0 if lock wasn't yours, NULL if no lock
        $result = $this->queryBuilder->raw("SELECT RELEASE_LOCK(?)", [$lockName]);
        //TODO : VERIFY THE RESULT OF RELEASE_LOCK, IT'S ARRAY NOT BOOLEAN
        return (int)$result === 1;
    }
}
