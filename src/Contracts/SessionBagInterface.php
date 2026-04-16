<?php

declare(strict_types=1);

namespace MonkeysLegion\Session\Contracts;

/**
 * Defines a container for session data to segment logic between attributes, flash data, and metadata.
 */
interface SessionBagInterface
{
    /**
     * The name identifying this bag.
     */
    public string $name { get; }

    /**
     * Initialize the bag with existing data.
     *
     * @param array<string, mixed> $array
     */
    public function initialize(array &$array): void;

    /**
     * Get the storage key for this bag in the session.
     */
    public function getStorageKey(): string;

    /**
     * Clear the bag and return its previous contents.
     *
     * @return array<string, mixed>
     */
    public function clear(): array;
}
