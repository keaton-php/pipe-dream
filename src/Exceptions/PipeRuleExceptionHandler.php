<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;
use R1kon\PipeDream\PipeEnvelope;
use Illuminate\Container\Container;

class PipeRuleExceptionHandler
{
    
    protected $handlers = [];

    protected ?Container $app;
    
    //This holds all of the exceptions and handles them
    //And really should return something of note.
    public function __construct(?Container $app = null)
    {
        if($this->app = $app ?? app())
            $this->bootHandlers();

    }

    public function addHandler($exception, $handler)
    {
        $this->handlers[$exception] = $handler;
    }

    public function handlers()
    {
        return $this->handlers;
    }

    public function bootHandlers()
    {
        $this->handlers = $this->app->make('pipehub:exceptions');
    }

    public function handle(PipeEnvelope $subject, Exception $exception)
    {
        //This is a placeholder for now.
        if( ! $exceptionHandler = $this->handlers[get_class($exception)] ?? null)
            return null;

        return $exceptionHandler::handle($subject, $exception);
    }

}