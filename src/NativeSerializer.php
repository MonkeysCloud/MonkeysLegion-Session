<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\Session\Contracts\DataHandlerInterface;

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
        return unserialize($data);
    }
}
