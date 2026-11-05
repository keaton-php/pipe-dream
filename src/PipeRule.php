<?php

namespace Keaton\PipeDream;

use BadMethodCallException;
use Closure;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Keaton\PipeDream\Concerns\PassesSubjectToCallbacks;
use Keaton\PipeDream\Exceptions\PipeRuleError;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Throwable;

/**
 * One step of a flow. Extend it and add a public method named after the
 * class (or name it in static $runFunction); it is called through the
 * container, so it can type-hint its dependencies, and receives the
 * envelope as $subject.
 *
 * Return SUCCESS, FAIL, FATAL_FAIL or SKIP (anything else is recorded as
 * it is), or call $this->error() / $this->fatal() / $this->skip(). The
 * result is saved on the envelope under the rule's class name (and under
 * static $saveResultAs when set).
 */
abstract class PipeRule
{
    use PassesSubjectToCallbacks;

    const ERROR = 'error';
    const FATAL = 'fatal';

    public const SUCCESS = 1;
    public const FAIL = 2;
    public const FATAL_FAIL = 3;
    public const SKIP = 4;

    public static $name = 'PipeRule';
    public static $description = 'New base pipe rule';
    public static $runFunction = null;
    public static $saveResultAs = null;

    protected $defaultResult = self::SUCCESS;

    /** Rethrow unexpected exceptions instead of logging them (see withoutErrorHandling()). */
    protected $rethrowErrors = false;

    /** Treat a PipeRuleError raised by this rule as a PipeRuleFatal. */
    protected $errorsAreFatal = false;

    /** End the hub when this rule raises a PipeRuleError (a hub's stopOnError() also applies, per run). */
    protected $stopOnError = false;

    protected $shouldSkip = false;

    protected ?string $skipReason = null;

    protected bool $skippedItself = false;

    protected bool $wasSkipped = false;

    /*
     * Per-run policy, written by the hub on every run (see applyPolicy()).
     * Kept apart from the rule's own settings so a container-shared rule
     * instance never carries one hub's policy into another run.
     */
    protected bool $policySkip = false;

    protected ?string $policySkipReason = null;

    protected bool $policyStopOnError = false;

    /** Set by withoutErrorHandling() for the current run only. */
    protected bool $runRethrowErrors = false;

    protected ?Container $container = null;

    protected $subject;

    public function __construct(?bool $errorsAreFatal = null, ?bool $stopOnError = null, ?bool $shouldSkip = null)
    {
        // Only override the class defaults when told to, so a subclass can
        // declare `protected $errorsAreFatal = true;`.
        $this->errorsAreFatal = $errorsAreFatal ?? $this->errorsAreFatal;
        $this->stopOnError = $stopOnError ?? $this->stopOnError;
        $this->shouldSkip = $shouldSkip ?? $this->shouldSkip;
    }

    /**
     * Mark this rule to be skipped: it is logged as SKIP and never executed.
     */
    public function shouldSkip($skip = true, ?string $reason = null)
    {
        $this->shouldSkip = $skip;
        $this->skipReason = $reason;

        return $this;
    }

    /**
     * Whether this rule will be skipped (not executed) for the given subject.
     */
    public function willSkip($subject): bool
    {
        return $this->shouldSkip
            || $this->policySkip
            || ($subject instanceof PipeEnvelope && $subject->skipsAll());
    }

    /**
     * The hub's policy for the coming run. Every field is written on every
     * call, so nothing from an earlier run survives.
     *
     * @internal Called by PipeHub before each run.
     */
    public function applyPolicy(bool $skip, ?string $skipReason, bool $stopOnError)
    {
        $this->policySkip = $skip;
        $this->policySkipReason = $skip ? $skipReason : null;
        $this->policyStopOnError = $stopOnError;

        return $this;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Whether the last run skipped this rule without executing it.
     */
    public function wasSkipped(): bool
    {
        return $this->wasSkipped;
    }

    public function setStopOnError(bool $stop = true)
    {
        $this->stopOnError = $stop;

        return $this;
    }

    /**
     * The key this rule's log records and result are stored under.
     */
    public function ruleKey(): string
    {
        return static::class;
    }

    public function getExtendedClassMethods()
    {
        $extendedClass = get_called_class();
        $methods = (new \ReflectionClass($extendedClass))->getMethods(\ReflectionMethod::IS_PUBLIC);

        $extendedClassMethods = [];
        foreach ($methods as $method) {
            if ($method->class === $extendedClass) {
                $extendedClassMethods[] = $method->name;
            }
        }

        return $extendedClassMethods;
    }

    private function getExecuteFunctionName()
    {
        if (static::$runFunction && is_callable([$this, static::$runFunction])) {
            return static::$runFunction;
        }

        $expected = Str::upper(class_basename($this));

        foreach ($this->getExtendedClassMethods() as $method) {
            if (Str::upper($method) === $expected) {
                return $method;
            }
        }

        return null;
    }

    /**
     * The container the rule's method is called through: the hub's. A rule
     * driven by something other than a PipeHub falls back to the global one.
     */
    protected function container(): Container
    {
        return $this->container ?? IlluminateContainer::getInstance();
    }

    protected function skipMessage(): string
    {
        if ($this->shouldSkip) {
            return $this->skipReason ?? 'Skipped';
        }

        return $this->policySkip
            ? ($this->policySkipReason ?? 'Skipped')
            : 'Skipped: skipAll() was called';
    }

    protected function runFromContainer($method, $parameters = [])
    {
        if (method_exists($this, $method)) {
            return $this->container()->call([$this, $method], $parameters);
        }

        return null;
    }

    public function execute($subject)
    {
        $this->skippedItself = false;

        if (! $method = $this->getExecuteFunctionName()) {
            $class = new \ReflectionClass($this);

            throw (new PipeRuleError(sprintf(
                'Rule %s has no run method: add a public method named %s() or set static $runFunction',
                static::class,
                lcfirst(class_basename($this))
            ), $subject))->setOrigin((string) $class->getFileName(), (int) $class->getStartLine());
        }

        $result = $this->container()->call([$this, $method], $this->withSubjectParameter([$this, $method], ['subject' => $subject])) ?? $this->defaultResult;

        $this->logExecutionResult($subject, $this->ruleKey(), $this->skippedItself ? self::SKIP : $result);

        return $subject;
    }

    protected function logExecutionResult($subject, $key, $result)
    {
        $subject->saveRuleResult(new PipeRuleResult($key, $result));
        $subject->set($key, $result);

        if (static::$saveResultAs) {
            $subject->set(static::$saveResultAs, $result);
        }
    }

    public function handle($subject, Closure $next)
    {
        $this->register($subject);
        $this->runRethrowErrors = false;
        $this->wasSkipped = $this->willSkip($subject);

        try {
            if ($this->wasSkipped) {
                $this->info($this->skipMessage());
                $this->logExecutionResult($subject, $this->ruleKey(), self::SKIP);
            } else {
                $subject = $this->execute($subject) ?? $subject;
            }
        } catch (PipeRuleError $e) {
            if ($this->errorsAreFatal) {
                $this->logExecutionResult($this->subject, $this->ruleKey(), self::FATAL_FAIL);
                $this->registerException($e, self::FATAL);

                throw (new PipeRuleFatal($e->getMessage(), $this->subject, $e->getCode(), $e))
                    ->setOrigin($e->originFile(), $e->originLine());
            }

            $this->logExecutionResult($this->subject, $this->ruleKey(), self::FAIL);
            $this->registerException($e);

            if ($this->stopOnError || $this->policyStopOnError) {
                throw $e;
            }
        } catch (PipeRuleFatal $e) {
            $this->logExecutionResult($this->subject, $this->ruleKey(), self::FATAL_FAIL);
            $this->registerException($e, self::FATAL);

            throw $e;
        } catch (PipeRuleRedirect $e) {
            throw $e->resolveRoute($this->container());
        } catch (PipeRuleLoadView $e) {
            throw $e;
        } catch (Throwable $e) {
            // Anything else, including PHP errors such as a TypeError.
            $this->logExecutionResult($this->subject, $this->ruleKey(), self::FAIL);
            $this->registerException($e);

            if ($this->rethrowErrors || $this->runRethrowErrors) {
                throw $e;
            }
        } finally {
            $this->unregister();
        }

        return $next($subject);
    }

    /** @deprecated use ruleKey() */
    protected function ruleClass()
    {
        return $this->ruleKey();
    }

    /**
     * Let unexpected exceptions escape this rule (the hub then treats them as
     * a failure) instead of logging them and carrying on.
     */
    protected function withoutErrorHandling()
    {
        $this->runRethrowErrors = true;
    }

    protected function registerException($exception, $type = self::ERROR)
    {
        $this->subject->registerException($exception, $type, $this->ruleKey());
    }

    protected function register($subject)
    {
        $this->subject = $subject;
        $this->subject->register($this);
    }

    protected function unregister()
    {
        $this->subject->unregister($this);
    }

    protected function info($message)
    {
        $this->log('info', $message);
    }

    protected function warning($message)
    {
        $this->log('warning', $message);
    }

    /**
     * Stop: the result is FATAL_FAIL and the hub ends (the flow too, with
     * stopOnFatal()).
     */
    protected function fatal($message)
    {
        throw (new PipeRuleFatal($message, $this->subject))->setOrigin(...$this->callSite());
    }

    /**
     * Fail: the result is FAIL and the hub carries on (unless stopOnError()
     * or $errorsAreFatal).
     */
    protected function error($message)
    {
        throw (new PipeRuleError($message, $this->subject))->setOrigin(...$this->callSite());
    }

    /**
     * [file, line] of the first call outside this class's own file: the line
     * in the user's rule that called fatal() or error().
     */
    protected function callSite(): array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                return [$frame['file'], $frame['line'] ?? 0];
            }
        }

        return [__FILE__, __LINE__];
    }

    /**
     * Record this rule as skipped, with a reason: `return $this->skip('...')`.
     */
    protected function skip($message = null)
    {
        $this->skippedItself = true;

        if ($message) {
            $this->info($message);
        }

        return self::SKIP;
    }

    protected function log($level, $message)
    {
        $this->subject->logger()->{$level}($this->ruleKey(), $message);
    }

    public function __set($name, $value)
    {
        $this->subject->set($name, $value);
    }

    public function __get($name)
    {
        return $this->subject?->get($name);
    }

    /**
     * set{Name}Result($value) / get{Name}Result() store and read shared state
     * on the envelope under the snake_cased name: setBotScoreResult(0.4) is
     * set('bot_score', 0.4).
     */
    public function __call($method, $arguments)
    {
        if (preg_match('/^(set|get)([A-Za-z0-9]+)Result$/', $method, $matches)) {
            $key = Str::snake($matches[2]);

            if (! $this->subject instanceof PipeEnvelope) {
                throw new BadMethodCallException("{$method}() can only be used while the rule is running.");
            }

            return $matches[1] === 'set'
                ? $this->subject->set($key, $arguments[0] ?? null)
                : $this->subject->get($key);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
    }
}
