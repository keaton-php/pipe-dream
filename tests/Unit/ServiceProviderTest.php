<?php

namespace R1kon\PipeDream\Tests\Unit;

use Illuminate\Support\ServiceProvider;
use R1kon\PipeDream\PipeDreamServiceProvider;
use R1kon\PipeDream\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_migration_is_publishable(): void
    {
        $paths = ServiceProvider::pathsToPublish(PipeDreamServiceProvider::class, 'pipe-dream-migrations');

        $this->assertCount(1, $paths);
        $this->assertFileExists(array_key_first($paths));
        $this->assertStringEndsWith('migrations/'.PipeDreamServiceProvider::MIGRATION, array_values($paths)[0]);
    }

    public function test_it_registers_an_empty_exception_handler_map(): void
    {
        $this->assertSame([], $this->app->make('pipehub:exceptions'));
    }
}
