<?php

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

// Usually in a service provider's register() method, as $this->app->bind(...).
app()->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);
