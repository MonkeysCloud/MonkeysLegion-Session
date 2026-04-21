<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Drivers;

use MonkeysLegion\Database\Contracts\ConnectionManagerInterface;
use MonkeysLegion\Database\Types\DatabaseDriver as DriverType;
use MonkeysLegion\Query\Query\QueryBuilder;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;

/**
 * Database session driver.
 */
class DatabaseDriver implements SessionDriverInterface
{
    private QueryBuilder $queryBuilder;
    private string $table;

    private const string LOCK_KEY = 'ml_session_';

    /**
     * @param ConnectionManagerInterface $connectionManager
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connectionManager,
        private readonly array $config
    ) {
        $this->queryBuilder = new QueryBuilder($this->connectionManager);
        /** @var mixed $table */
        $table = $config['table'] ?? 'sessions';
        $this->table = is_string($table) ? $table : 'sessions';
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
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): ?array
    {
        /** @var array<string, mixed>|null $session */
        $session = $this->queryBuilder
            ->from($this->table)
            ->where('session_id', '=', $id)
            ->first();

        if ($session === null) {
            return null;
        }

        /** @var mixed $expiration */
        $expiration = $session['expiration'] ?? null;
        if ($expiration !== null && is_numeric($expiration) && time() > (int)$expiration) {
            $this->destroy($id);
            return null;
        }

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $payload, array $metadata): bool
    {
        /** @var mixed $lifetimeVal */
        $lifetimeVal = $this->config['lifetime'] ?? 7200;
        $lifetime = is_numeric($lifetimeVal) ? (int)$lifetimeVal : 7200;
        $now = time();

        $data = [
            'payload' => $payload,
            'last_activity' => $now,
            'expiration' => $now + $lifetime,
        ];

        $allowedColumns = ['flash_data', 'user_id', 'ip_address', 'user_agent'];
        foreach ($allowedColumns as $col) {
            if (array_key_exists($col, $metadata)) {
                $data[$col] = $metadata[$col];
            }
        }

        $exists = $this->queryBuilder
            ->from($this->table)
            ->where('session_id', '=', $id)
            ->count() > 0;

        if ($exists) {
            return $this->queryBuilder
                ->table($this->table)
                ->where('session_id', '=', $id)
                ->update($data) > 0;
        }

        $data['session_id'] = $id;
        $data['created_at'] = $now;

        /** @var array<string, mixed> $data */
        return (bool)$this->queryBuilder
            ->table($this->table)
            ->insert($data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        return $this->queryBuilder
            ->table($this->table)
            ->where('session_id', '=', $id)
            ->delete() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): int|false
    {
        $threshold = time() - $maxLifetime;

        $count = (int)$this->queryBuilder
            ->table($this->table)
            ->where('last_activity', '<', $threshold)
            ->count();

        if ($count === 0) {
            return 0;
        }

        $deleted = $this->queryBuilder
            ->table($this->table)
            ->where('last_activity', '<', $threshold)
            ->delete();

        return $deleted > 0 ? $count : false;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $id, int $timeout = 30): bool
    {
        $lockName = self::LOCK_KEY . $id;
        $conn = $this->connectionManager->connection();
        $driver = $conn->getDriver();

        $sql = match ($driver) {
            DriverType::MySQL => 'SELECT GET_LOCK(:name, :timeout)',
            DriverType::PostgreSQL => 'SELECT pg_try_advisory_lock(hashtext(:name))',
            default => 'SELECT 1 as lock_status',
        };

        $params = match ($driver) {
            DriverType::MySQL => [':name' => $lockName, ':timeout' => $timeout],
            DriverType::PostgreSQL => [':name' => $lockName],
            default => [],
        };

        /** @var mixed $result */
        $result = $conn->query($sql, $params)->fetchColumn();

        return (bool)$result;
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string $id): bool
    {
        $lockName = self::LOCK_KEY . $id;
        $conn = $this->connectionManager->connection();
        $driver = $conn->getDriver();

        $sql = match ($driver) {
            DriverType::MySQL => 'SELECT RELEASE_LOCK(:name)',
            DriverType::PostgreSQL => 'SELECT pg_advisory_unlock(hashtext(:name))',
            default => 'SELECT 1 as lock_status',
        };

        $params = match ($driver) {
            DriverType::MySQL, DriverType::PostgreSQL => [':name' => $lockName],
            default => [],
        };

        /** @var mixed $result */
        $result = $conn->query($sql, $params)->fetchColumn();

        return (bool)$result;
    }
}

