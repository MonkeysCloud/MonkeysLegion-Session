<?php

declare(strict_types=1);

namespace MonkeysLegion\Session;

use MonkeysLegion\DI\Contracts\ServiceProviderInterface;
use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Drivers\FileDriver;

/**
 * Registers Session package interface bindings.
 *
 * Default driver: FileDriver (override via container definitions
 * or .env config for Redis/Database drivers).
 */
final class SessionServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->bind(SessionDriverInterface::class, FileDriver::class);
    }
}
