<?php

namespace Keaton\PipeDream\Exceptions;

use Illuminate\Contracts\Container\Container;
use Keaton\PipeDream\PipeEnvelope;
use Throwable;

/**
 * Routes an exception a rule raised to the handler registered for it. A
 * handler registered for a parent class (or interface) also handles its
 * subclasses; an exact match always wins over a parent match.
 */
class PipeRuleExceptionHandler
{
    protected array $handlers = [];

    protected ?Container $app;

    public function __construct(?Container $app = null)
    {
        $this->app = $app ?? \Illuminate\Container\Container::getInstance();

        $this->bootHandlers();
    }

    public function addHandler($exception, $handler)
    {
        $this->handlers[$exception] = $handler;

        return $this;
    }

    public function handlers()
    {
        return $this->handlers;
    }

    public function bootHandlers()
    {
        $this->handlers = $this->app && $this->app->bound('pipehub:exceptions')
            ? (array) $this->app->make('pipehub:exceptions')
            : [];
    }

    public function handlerFor(Throwable $exception)
    {
        if (isset($this->handlers[get_class($exception)])) {
            return $this->handlers[get_class($exception)];
        }

        foreach ($this->handlers as $class => $handler) {
            if ($exception instanceof $class) {
                return $handler;
            }
        }

        return null;
    }

    public function handle(PipeEnvelope $subject, Throwable $exception)
    {
        if (! $handler = $this->handlerFor($exception)) {
            return null;
        }

        return $handler::handle($subject, $exception);
    }
}
