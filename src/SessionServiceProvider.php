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
 * Default driver: FileDriver with a sensible default path.
 * Override via container definitions for Redis/Database drivers.
 */
final class SessionServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Register FileDriver with its required $path parameter
        $builder->set(FileDriver::class, function () {
            $path = getenv('SESSION_PATH') ?: sys_get_temp_dir() . '/monkeyslegion_sessions';
            return new FileDriver($path);
        });

        // Bind the interface to the concrete class
        $builder->bind(SessionDriverInterface::class, FileDriver::class);
    }
}

