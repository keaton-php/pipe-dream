<?php

namespace R1kon\PipeDream;

use Illuminate\Support\ServiceProvider;

class PipeDreamServiceProvider extends ServiceProvider
{
    public const MIGRATION = '2026_11_05_000000_create_pipe_dream_logs_table.php';

    public function register(): void
    {
        // Default: no custom rule-exception handlers. Apps can rebind this to
        // an [ExceptionClass => HandlerClass] map.
        $this->app->singletonIf('pipehub:exceptions', fn () => []);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $paths = [
            __DIR__.'/../database/migrations/'.self::MIGRATION => $this->app->databasePath('migrations/'.self::MIGRATION),
        ];

        // publishesMigrations() (Laravel 11+) re-dates the file on publish.
        method_exists($this, 'publishesMigrations')
            ? $this->publishesMigrations($paths, 'pipe-dream-migrations')
            : $this->publishes($paths, 'pipe-dream-migrations');
    }
}
