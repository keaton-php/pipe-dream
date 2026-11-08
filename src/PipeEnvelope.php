<?php

namespace Keaton\PipeDream;

use BadMethodCallException;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Exceptions\PipeRuleExceptionHandler;
use Throwable;

/**
 * The subject travels through a flow wrapped in an envelope. The envelope
 * carries the log, the rule results, shared state (set/get) and a stable
 * run id; property reads, writes and method calls are passed through to the
 * wrapped subject.
 */
class PipeEnvelope
{
    protected $subject;

    protected $logger;

    protected $savedAttributes = [];

    protected $ruleResults;

    protected $app;

    protected $exceptionHandler;

    protected bool $skipAllRemainingPipes = false;

    protected string $runId;

    protected ?string $flowName = null;

    public function __construct($subject = null, $app = null)
    {
        $this->subject = $subject;
        $this->app = $app ?? Container::getInstance();
        $this->runId = (string) Str::uuid();

        $this->logger = new PipeLogger($this->app);
        $this->ruleResults = new PipeRuleResults();
        $this->exceptionHandler = new PipeRuleExceptionHandler($this->app);
    }

    /**
     * Wrap a subject, or hand back an envelope unchanged. $app is the
     * container a new envelope resolves its services from.
     */
    public static function wrap($subject, $app = null)
    {
        return $subject instanceof PipeEnvelope ? $subject : new static($subject, $app);
    }

    public function container()
    {
        return $this->app;
    }

    /**
     * A uuid that identifies this run, stable for the life of the envelope.
     * DatabasePersistStrategy upserts on it.
     */
    public function runId(): string
    {
        return $this->runId;
    }

    /**
     * The name of the flow this envelope is travelling through (the
     * manager's name, or the hub's name when a hub runs on its own).
     */
    public function flowName(): ?string
    {
        return $this->flowName;
    }

    public function setFlowName(?string $name)
    {
        $this->flowName = $name;

        return $this;
    }

    public function persist()
    {
        return $this->logger->persist($this);
    }

    public function persistLogs(?PipeLoggerPersistStrategy $strategy = null)
    {
        return $this->logger->persist($this, $strategy);
    }

    /**
     * @param  PipeLoggerPersistStrategy|class-string<PipeLoggerPersistStrategy>  $strategy
     */
    public function persistWith(PipeLoggerPersistStrategy|string $strategy)
    {
        if (is_string($strategy)) {
            if (! class_exists($strategy) || ! is_subclass_of($strategy, PipeLoggerPersistStrategy::class)) {
                throw new InvalidArgumentException(
                    "Persist strategy [{$strategy}] must be a class implementing ".PipeLoggerPersistStrategy::class.'.'
                );
            }

            $strategy = $this->app ? $this->app->make($strategy) : new $strategy();
        }

        $this->logger->persistLogsWith($strategy);

        return $this;
    }

    /**
     * One of PipeLogger::LEVEL_EVERYTHING, LEVEL_NO_TIMERS, LEVEL_PROBLEMS.
     */
    public function logLevel($level)
    {
        $this->logger->setLevel($level);

        return $this;
    }

    public function subject()
    {
        return $this->subject;
    }

    /**
     * A rule (or closure pipe) is starting: open its timer.
     */
    public function register($rule)
    {
        $key = $this->keyFor($rule);
        $this->logger->timerStart($key, "Executing rule {$key}");
    }

    /**
     * A rule (or closure pipe) has finished: close its timer.
     */
    public function unregister($rule)
    {
        $key = $this->keyFor($rule);
        $this->logger->timerEnd($key, "Finished executing rule {$key}");
    }

    protected function keyFor($rule): string
    {
        return $rule instanceof PipeRule ? $rule->ruleKey() : get_class($rule);
    }

    public function addExceptionHandler($exception, $handler)
    {
        $this->exceptionHandler->addHandler($exception, $handler);

        return $this;
    }

    public function registerException(Throwable $exception, $type, $ruleClass)
    {
        // A rule's own fatal()/error() records the rule's call site, not PipeRule.
        $file = method_exists($exception, 'originFile') ? $exception->originFile() : $exception->getFile();
        $line = method_exists($exception, 'originLine') ? $exception->originLine() : $exception->getLine();

        $exceptionMessage = sprintf('%s [%s | line %s]', $exception->getMessage(), $file, $line);
        $this->logger->{$type}($ruleClass, $exceptionMessage, [
            'exception' => get_class($exception),
            'file' => $file,
            'line' => $line,
        ]);

        return $this->exceptionHandler->handle($this, $exception);
    }

    public function saveRuleResult(PipeRuleResult $pipeExecutionResult)
    {
        $this->logger->returnValue($pipeExecutionResult->rule(), $pipeExecutionResult->result());

        $this->ruleResults->add($pipeExecutionResult);
    }

    /**
     * Skip everything that has not run yet: every remaining pipe in this hub
     * and in every later hub is logged as skipped and not executed.
     */
    public function skipAll()
    {
        $this->skipAllRemainingPipes = true;

        return $this;
    }

    public function skipsAll(): bool
    {
        return $this->skipAllRemainingPipes;
    }

    public function results()
    {
        return $this->ruleResults;
    }

    public function set($key, $value)
    {
        $this->savedAttributes[$key] = $value;

        return $this;
    }

    /**
     * Read shared state. Keys are matched exactly first, then
     * case-insensitively. With no key, every saved value is returned.
     */
    public function get($key = null)
    {
        // Only null means "everything": 0, '0' and '' are keys like any other.
        if ($key === null) {
            return $this->savedAttributes;
        }

        if (array_key_exists($key, $this->savedAttributes)) {
            return $this->savedAttributes[$key];
        }

        return $this->ignoreSavedAttributeCase($key);
    }

    public function result($key = null)
    {
        return $this->get($key);
    }

    private function ignoreSavedAttributeCase($key)
    {
        foreach ($this->savedAttributes as $savedKey => $value) {
            if (Str::upper((string) $savedKey) === Str::upper((string) $key)) {
                return $value;
            }
        }

        return null;
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
        return $this->logger->errors()->isNotEmpty();
    }

    public function hasFatalErrors()
    {
        return $this->logger->fatals()->isNotEmpty();
    }

    public function __set($name, $value)
    {
        if (! is_object($this->subject)) {
            throw new BadMethodCallException("Cannot set [{$name}]: the envelope has no subject object.");
        }

        $this->subject->$name = $value;
    }

    public function __get($name)
    {
        if (is_object($this->subject) && isset($this->subject->$name)) {
            return $this->subject->$name;
        }

        return null;
    }

    public function __isset($name)
    {
        return is_object($this->subject) && isset($this->subject->$name);
    }

    public function __call($name, $arguments)
    {
        // is_callable() also accepts what the subject answers through its own
        // __call() (an Eloquent scope, a forwarded query method), and refuses
        // a method the envelope may not call.
        if (is_object($this->subject) && is_callable([$this->subject, $name])) {
            return $this->subject->$name(...$arguments);
        }

        throw new BadMethodCallException("Method [{$name}] does not exist on the envelope or its subject.");
    }
}
