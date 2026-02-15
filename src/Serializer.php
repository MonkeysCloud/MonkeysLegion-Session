<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

class Serializer
{
    /**
     * Serialize session data into a string.
     *
     * @param mixed $data The session data
     * @return string The serialized data
     */
    public static function serialize(mixed $data): string
    {
        return serialize($data);
    }

    /**
     * Deserialize a string back into session data.
     *
     * @param string $data The serialized data
     * @return mixed The unserialized session data
     */
    public static function unserialize(string $data): mixed
    {
        return unserialize($data);
    }
}
