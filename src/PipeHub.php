<?php

namespace R1kon\PipeDream;

use R1kon\PipeDream\Exceptions\PipeRuleLoadView;
use Illuminate\Support\Arr;
use R1kon\PipeDream\PipeRule;
//use Illuminate\Pipeline\Pipeline;
use R1kon\PipeDream\Exceptions\PipeRuleRedirect;
use Illuminate\Contracts\Container\Container;
use R1kon\PipeDream\Exceptions\PipeRuleFatal;
use R1kon\PipeDream\Exceptions\PipeHubException;
use R1kon\PipeDream\PipeDreamPipeline as Pipeline;

class PipeHub
{

    protected $app;

    protected $name;

    protected $description;

    protected $subject;

    protected ?\Closure $thenCallback = null;

    protected $pipes = [];

    protected $instantiatedPipes = [];

    protected ?\Closure $skipIfCallback = null;

    protected $skipPipes = false;

    protected $skipThisHub = false;

    protected $broadcastEvents = [
        'before' => null,
        'after' => null,
        'skip' => null,
        'beforeEach' => null,
        'afterEach' => null
    ];

    protected $onFailure;

    protected $stopOnError = false;

    protected $stopOnFatal = false;

    protected $persistLogsOnFinalize = true;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public static function create(Container $app = null)
    {
        $app = $app ?? (function_exists('app') ? app() : null);
        return new static($app);
    }

    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    public function addPipe($pipe)
    {
        if(
            (class_exists($pipe) && is_subclass_of($pipe, PipeRule::class)) ||
            $pipe instanceof \Closure
        )
            $this->pipes[] = $pipe;

        return $this;
    }

    public function addPipes(array $pipes)
    {
        foreach($pipes as $pipe)
        {
            $this->addPipe($pipe);
        }
        
        return $this;
    }

    public function skipIf(\Closure $callback)
    {
        $this->skipIfCallback = $callback;
        return $this;
    }

    public function subject($subject)
    {
        $this->subject = PipeEnvelope::wrap($subject);
        return $this;
    }

    public function then(\Closure $callback)
    {
        $this->thenCallback = $callback;
        return $this;
    }

    private function runSkipIfCallback()
    {
        return $this->skipPipes = 
                $this->skipIfCallback 
                ? $this->callViaContainer($this->skipIfCallback, ['subject' => $this->subject]) 
                : false;
    }

    private function initializeSubject($subject)
    {
        $this->subject = $subject;

        $this->registerEventsInSubject();
        
        return $subject;
    }

    private function addSkipsToSubject()
    {
        $this->subject->setSkips($this->skipPipes);
    }

    private function shouldSkipPipe($pipe)
    {
        
    }

    public function skip($shouldSkip = true)
    {
        //Skip this whole pipe run.
        $this->skipThisHub = $shouldSkip;
        return $this;
    }

    private function pipesToSkip()
    {
        if($this->runSkipIfCallback())
            return $this->pipes;

        return [];
    }

    public function sendIt($subject = null)
    {
        if(!$subject = $subject ?? $this->subject)
            throw new PipeHubException("No subject provided");

        if(!$subject instanceof PipeEnvelope)
            $subject = $this->wrapInEnvelope($subject);

        $this->initializeSubject($subject);
        
        if($this->skipThisHub)
        {
            $this->registerPipeHubSkip();
            return $subject;
        }

        $pipesToSkip = $this->pipesToSkip();

        try 
        {
            $this->instantiatedPipes = array_map(function($pipe) use($pipesToSkip) {

                if($pipe instanceof \Closure)
                    return $this->wrapInClosure($pipe);

                return $this->app->make($pipe, [
                                    'stopOnError' => $this->stopOnError,
                                    'shouldSkip' => in_array($pipe, $pipesToSkip),
                                ]);
            }, $this->getPipes());

            $this->registerPipeHubStart();

            $subject = (new Pipeline($this->app))
                            ->send($this->runBeforePipelineHook($subject))
                            ->through($this->instantiatedPipes)
                            ->then($this->completePipeRun());

        } catch(PipeRuleFatal $e)
        {
            $this->handleException($e, "PipeHub:sendIt");
            throw $e;
        
        } catch(PipeRuleRedirect $e)
        {
            $this->logInfo("PipeHub:sendIt", "Redirecting to " . $e->redirectTo);
            throw $e;
        } catch(PipeRuleLoadView $e)
        {
            $this->logInfo("PipeHub:sendIt", "Loading view " . $e->view);
            throw $e;
        }
        catch(\Exception $e)
        {
            //TODO: handle exceptions here, how should we proceed?
            $this->handleException($e, "PipeHub:sendIt");
            if($this->onFailure instanceof \Closure)
                $this->callViaContainer($this->onFailure, ['subject' => $subject]);
        } finally {
            $this->registerPipeHubFinish();
        }

        return $subject;
    }

    public function persistLogs($persist = true)
    {
        $this->persistLogsOnFinalize = $persist;
        return $this;
    }

    public function onFailure(\Closure $callback)
    {
        $this->onFailure = $callback;
        return $this;
    }

    public function stopOnFatal($shouldStopOnFatal = true)
    {
        $this->stopOnFatal = $shouldStopOnFatal;
        return $this;
    }

    public function failureIsFatal()
    {
        $this->onFailure = function($subject) {
            throw new PipeRuleFatal("Failure is fatal, stopping pipeline", $subject);
        };

        return $this;
    }

    public function stopOnError()
    {
        $this->stopOnError = true;
        return $this;
    }

    private function registerEventsInSubject()
    {
        $this->subject->setEvents(Arr::only($this->broadcastEvents, ['beforeEach', 'afterEach']));
    }

    public function broadcastEventAfter($event)
    {
       $this->broadcastEvents['after'] = $event;
       return $this;
    }

    public function broadcastEventAfterEachPipe($event)
    {
        $this->broadcastEvents['afterEach'] = $event;
        return $this;
    }

    public function broadcastEventBeforeEachPipe($event)
    {
        $this->broadcastEvents['beforeEach'] = $event;
        return $this;
    }

    public function broadcastEventBefore($event)
    {
        $this->broadcastEvents['before'] = $event;
        return $this;
    }

    private function broadcastEvent($event)
    {
        $this->app->make('events')->dispatch(new $event($this->subject));
        return $this;
    }

    private function runBeforePipelineHook($subject)
    {
        //Run the hook before the pipeline starts, basically the closure specified in ->before(function($subject) {  });
        /*
        I don't think you need to return the subject in the closure, I think you can just do it, like the query does in a scope
        LOG LEVELS! SO WE CAN DO LIKE 'VERBOSE' WHERE IT'S ALL OF IT, 'DEBUG' WHERE IT'S DEBUGGING, 'NONE' WHERE IT'S NONE
        THAT KIND OF SHIT. THAT WAY WE CAN SAVE IT IN THE DB CORRECTLY.
        */
        return $subject;
    }

    private function handleException($exception, $key="PipeHub:sendIt")
    {
        $this->logError($key,$exception->getMessage(), [
            'exception' => get_class($exception)
        ]);
    }

    private function registerPipeHubSkip($key = "PipeHub:sendIt")
    {
        $this->logInfo($key, "Skipping hub", $this->getPipeHubContext());
        $this->sendEvent('skip');
    }

    private function registerPipeHubStart($key = "PipeHub:sendIt")
    {
        $this->logInfo($key,"Sending subject through all pipes", $this->getPipeHubContext());

        $this->sendEvent('before');
    }

    private function sendEvent($eventType)
    {
        if( ! $event = $this->broadcastEvents[$eventType] ?? null)
            return;

        $this->logInfo($this->getLogKey(), "Sending event before pipes [".$eventType."]");
        $this->broadcastEvent($event);
    }

    private function getLogKey()
    { 
        $trace = debug_backtrace();
        $caller = $trace[1];

        return $caller['class'] . ':' . $caller['function'];
    }

    private function registerPipeHubFinish($key = "PipeHub:sendIt")
    {
        $this->logInfo($key,"Finalized result!");

        if($this->persistLogsOnFinalize)
            $this->subject->persist();
    }

    protected function logInfo($key, $message, $context = [])
    {
        $this->subject->logger()->info($key, $message, $context);
    }

    protected function logError($key, $message, $context = [])
    {
        $this->subject->logger()->error($key, $message, $context);
    }

    private function getPipeHubContext()
    {
        return [
            'name' => $this->name,
            'description' => $this->description
        ];
    }

    private function completePipeRun()
    {
        return function($subject) {
            $this->sendEvent('after');
            return $this->executeThenCallback($subject);
        };
    }

    private function callViaContainer($callback, $parameters = [])
    {
        return $this->app->call($callback, $parameters);
    }

    private function executeThenCallback($subject)
    {
        try {
            if($this->thenCallback)
            {
                $callable = $this->thenCallback->bindTo($this, static::class);
                $this->callViaContainer($callable, ['subject' => $subject]);
            }
            
        } catch(\Exception $e)
        {
            $this->handleException($e, "PipeHub:executeThenCallback");
        }
        return $subject;
    }

    public function wrapInEnvelope($subject)
    {
        return (!$subject instanceof PipeEnvelope) ? new PipeEnvelope($subject) : $subject;
    }
    
    public function getInstantiatedPipes(): array
    {
        return $this->instantiatedPipes;
    }

    public function getPipes(): array
    {
        return $this->pipes;
    }

    private function wrapInClosure($callback)
    {
        return function($subject) use ($callback) {
            return $this->callViaContainer($callback, ['subject' => $subject]);
        };
    }

    public function description($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSubject(): PipeEnvelope
    {
        return $this->subject;
    }

}