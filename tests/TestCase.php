<?php

namespace R1kon\PipeDream\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use R1kon\PipeDream\PipeDreamServiceProvider;
use R1kon\PipeDream\Tests\Fixtures\Journal;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Journal::reset();
    }

    protected function getPackageProviders($app)
    {
        return [PipeDreamServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        // Every database test runs on an in-memory sqlite connection.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /** Record types for one key, in order. */
    protected function typesFor($envelope, string $key): array
    {
        return array_values(array_map(
            fn ($record) => $record['type'],
            array_filter($envelope->logs(), fn ($record) => $record['key'] === $key)
        ));
    }
}
