<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Contracts\DataHandlerInterface;

/**
 * Standard PHP serialization.
 */
class NativeSerializer implements DataHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function prepare(mixed $data): string
    {
        return serialize($data);
    }

    /**
     * {@inheritdoc}
     */
    public function restore(string $data): mixed
    {
        if ($data === '') {
            return [];
        }

        /** @var mixed $result */
        $result = @unserialize($data);
        
        return $result === false ? [] : $result;
    }
}
