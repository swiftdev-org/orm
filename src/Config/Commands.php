<?php namespace Swift\ORM\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Commands Configuration for Swift ORM
 */
class Commands extends BaseConfig
{
    /**
     * Commands to register
     */
    public array $commands = [
        'make:model' => \Swift\ORM\Commands\MakeModel::class,
        'make:entity' => \Swift\ORM\Commands\MakeEntity::class,
    ];
}

/**
 * Bootstrap function to register commands with CodeIgniter
 * Call this from your app's Config/Events.php or Services.php
 */
function registerSwiftOrmCommands()
{
    if (class_exists('\CodeIgniter\Commands\Utilities\Commands')) {
        \CodeIgniter\Commands\Utilities\Commands::$commands = array_merge(
            \CodeIgniter\Commands\Utilities\Commands::$commands ?? [],
            [
                'make:model' => \Swift\ORM\Commands\MakeModel::class,
                'make:entity' => \Swift\ORM\Commands\MakeEntity::class,
            ]
        );
    }
}
