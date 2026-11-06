<?php

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

// README:start
// In a service provider's register() method:
$this->app->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);
// README:end
