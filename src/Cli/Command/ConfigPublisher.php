<?php

namespace MonkeysLegion\Session\Cli\Command;

use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;

#[CommandAttr('session:publish', 'Publish the session configuration file to the project config directory')]
class ConfigPublisher extends Command
{
    /**
     * Execute the command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $src = __DIR__ . '/../../../config/session.php';
        $dest = 'config/session.php';

        if ($this->publish($src, $dest)) {
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}