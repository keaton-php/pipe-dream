<?php

namespace R1kon\PipeDream;

use Illuminate\Support\Str;
use R1kon\PipeDream\PipeLogger;
use R1kon\PipeDream\Contracts\PipeLoggerPersistStrategy;
use R1kon\PipeDream\Exceptions\PipeRuleExceptionHandler;

class PipeEnvelope {
    
    protected $subject;
    protected $logger;
    protected $broadcastEvents = [];
    protected $savedAttributes = [];
    protected $ruleResults;
    protected $skipPipes = [];
    protected $auditLogEntry;
    protected $logRuleResults = true;
    protected $app;
    protected $exceptionHandler;
    protected $skipAllRemainingPipes = false;

    public function __construct($subject = null, $app = null)
    { 
        $this->subject = $subject;
        $this->app = $app ?? app();

        $this->logger = new PipeLogger($this->app); //Here we go.
        $this->ruleResults = new PipeRuleResults(); //Here we go again.
        $this->exceptionHandler = new PipeRuleExceptionHandler($this->app); //and again!
    }

    public function __destruct()
    {
        
    }

    public function persist()
    {
        return $this->logger->persist($this);
    }

    public function persistLogs(?PipeLoggerPersistStrategy $strategy = null)
    {
        return $this->logger->persist($this, $strategy);
    }

    public function persistWith(PipeLoggerPersistStrategy|string $strategy)
    {
        if(is_string($strategy))
        {
            if(class_exists($strategy) && class_implements($strategy, PipeLoggerPersistStrategy::class))
                $strategy = new $strategy();
            else
                throw new \Exception("Invalid persist strategy");
        } 

        $this->logger->persistLogsWith($strategy);
        
        return $this;
    }

    public function logLevel($level)
    {
        $this->logger->setLevel($level);
        return $this;
    }
    
    public function subject()
    {
        return $this->subject;
    }

    public function register($rule)
    {
        //Initialize the logs for this rule
        $key = get_class($rule);
        $this->logger->timerStart($key, "Executing rule {$key}");
    }

    public function unregister($rule)
    {
        //End the logs for this rule
        $key = get_class($rule);
        $this->logger->timerEnd($key, "Finished executing rule {$key}");
    }

    public function addExceptionHandler($exception, $handler)
    {
        $this->exceptionHandler->addHandler($exception, $handler);
        return $this;
    }

    public function registerException(\Exception $exception, $type, $ruleClass)
    {
       $exceptionMessage = sprintf("%s [%s | line %s]", $exception->getMessage(), $exception->getFile(), $exception->getLine());
       $this->logger->{$type}($ruleClass, $exceptionMessage);
       return $this->exceptionHandler->handle($this, $exception);
    }

    public function saveRuleResult(PipeRuleResult $pipeExecutionResult)
    {
        if($this->logRuleResults)
            $this->logger->returnValue($pipeExecutionResult->rule(), $pipeExecutionResult->result());

        $this->ruleResults->add($pipeExecutionResult);
    }

    public function setSkips($skipPipes = [])
    {
        $this->skipPipes = $skipPipes;
    }

    public function skipAll()
    {
        $this->skipAllRemainingPipes = true;
        return $this;
    }

    public function results()
    {
        return $this->ruleResults;
    }

    public function set($key, $value)
    {
        $this->savedAttributes[$key] = $value;
    }

    private function ignoreSavedAttributeCase($key)
    {
        return collect($this->savedAttributes)->filter(function($value, $savedKey) use ($key) {
            return Str::upper($savedKey) === Str::upper($key);
        })->first();
    }
    
    public function result($key = null)
    {
        return $this->get($key);
    }

    public function broadcastEvents($type = null)
    {
        return $type ? ($this->broadcastEvents[$type] ?? null)
                     : $this->broadcastEvents;
    }

    public function setEvents($events)
    {
        $this->broadcastEvents = $events;
        return $this;
    }
    
    public function sendBroadcastEventByType($type)
    {
        if($this->broadcastEvents[$type] ?? null)
            $this->broadcastEvent($this->broadcastEvents[$type] ?? null);
        
            return $this;
    }

    private function broadcastEvent($event)
    {
        $this->resolve('events')->dispatch(new $event($this->subject));
        return $this;
    }

    private function resolve($key)
    {
        return $this->app->make($key);
    }

    public function get($key = null)
    {
        if(!$key)
            return $this->savedAttributes;

        if(! isset($this->savedAttributes[$key]))
        {
            return $this->ignoreSavedAttributeCase($key);
        }
        return $this->savedAttributes[$key] ?? null;
    }

    public static function wrap($subject)
    {
        //If the subject is already an envelope, just return. Otherwise, wrap.
        return $subject instanceof static 
                    ? $subject 
                    : new static($subject);
    }
    
    public function logger()
    {
        return $this->logger;
    }

    public function logs()
    {
        return $this->logger->records();
    }

    public function hasErrors()
    {
        return !! $this->logger->errors();
    }

    public function hasFatalErrors()
    {
        return !! $this->logger->fatals();
    }

    public function load($key)
    {
        return $this->evaluate($this->lazyLoaded[$key] ?? null);
    }
    
    public function evaluate($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }

    public function __set($name, $value)
    {
        if ($this->subject) {
            $this->subject->$name = $value;
        } else {
            throw new \Exception("Subject does not exist [$name]");
        }
    }

    public function __get($name)
    {
        if ($this->subject) {
            return isset($this->subject->$name)
                        ? $this->subject->$name
                        : null;
                       // : throw new \Exception("Property $name does not exist on subject");
        }
        return null;
        //throw new \Exception("Subject does not exist");
    }

    public function __call($name, $arguments)
    {
        if ($this->subject && method_exists($this->subject, $name)) {
            return $this->subject->$name(...$arguments);
        } else {
            throw new \Exception("Method $name does not exist on subject");
        }
    }
}
