<?php

namespace R1kon\PipeDream;

use Illuminate\Support\ServiceProvider;
use R1kon\PipeDream\Contracts\PipeLoggerPersistStrategy;

class PipeDreamServiceProvider extends ServiceProvider
{
   
    /**
     * Register services.
     */
    public function register(): void
    {
        // Default: no custom rule-exception handlers. Apps can rebind this to
        // an [ExceptionClass => HandlerClass] map.
        $this->app->singletonIf('pipehub:exceptions', fn () => []);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
