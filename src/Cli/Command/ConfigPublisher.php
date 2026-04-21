<?php

namespace MonkeysLegion\Session\Cli\Command;

use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Traits\Cli;

#[CommandAttr('session:publish', 'Publish the session configuration file to the project config directory')]
class ConfigPublisher extends Command
{
    use Cli;

    /**
     * Execute the command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $this->cliLine()->info('Publishing Session Configuration...')->print();

        $format = $this->choice('Which configuration format would you like to use?', ['mlc', 'php'], 0);
        $answers = [
            0 => 'mlc',
            1 => 'php'
        ];
        $src = __DIR__ . "/../../../config/session.{$answers[$format]}";
        $dest = "config/session.{$answers[$format]}";

        if ($this->publish($src, $dest)) {
            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    protected function publish(string $source, string $destination): bool
    {
        if (!is_file($source)) {
            $this->cliLine()
                ->error('Source config file not found: ')
                ->add($source, 'yellow')
                ->printError();
            return false;
        }

        $directory = dirname($destination);
        if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->cliLine()
                ->error('Could not create destination directory: ')
                ->add($directory, 'yellow')
                ->printError();
            return false;
        }

        if (file_exists($destination)) {
            $confirm = $this->confirm("File [{$destination}] already exists. Overwrite?", false);
            if (!$confirm) {
                $this->cliLine()->warning('Skipping publication.')->print();
                return true;
            }
        }

        if (!copy($source, $destination)) {
            $this->cliLine()
                ->error('Failed to publish config to: ')
                ->add($destination, 'yellow')
                ->printError();
            return false;
        }

        $this->cliLine()
            ->success('Published config to ')
            ->add($destination, 'green', 'underline')
            ->print();

        return true;
    }
}
