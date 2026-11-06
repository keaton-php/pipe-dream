<?php

namespace Keaton\PipeDream;

use Closure;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\Container;
use Keaton\PipeDream\Concerns\PassesSubjectToCallbacks;
use Keaton\PipeDream\Exceptions\PipeHubException;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeDreamPipeline as Pipeline;
use Throwable;

/**
 * A named stage of a flow: an ordered list of rules (or closures) the
 * subject passes through, with per-stage control over skipping, how far a
 * fatal reaches and whether errors are fatal, onError() / onFatal() hooks,
 * a then() callback and events.
 *
 * A fatal ends the whole flow by default; in a hub with
 * fatalsStopThisHubOnly() it ends only that hub.
 */
class PipeHub
{
    use PassesSubjectToCallbacks;

    const EVENTS = ['before', 'after', 'skip', 'beforeEach', 'afterEach'];

    protected $app;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?PipeEnvelope $subject = null;

    protected ?Closure $thenCallback = null;

    protected array $pipes = [];

    protected array $instantiatedPipes = [];

    protected ?Closure $skipIfCallback = null;

    protected bool $skipThisHub = false;

    protected array $broadcastEvents = [
        'before' => null,
        'after' => null,
        'skip' => null,
        'beforeEach' => null,
        'afterEach' => null,
    ];

    protected ?Closure $onError = null;

    protected ?Closure $onFatal = null;

    protected bool $fatalsStopThisHubOnly = false;

    protected bool $errorsAreFatal = false;

    protected bool $persistLogsOnFinalize = true;

    protected bool $throwFatals = false;

    public function __construct(?Container $app = null)
    {
        $this->app = $app ?? IlluminateContainer::getInstance();
    }

    public static function create(?Container $app = null)
    {
        return new static($app);
    }

    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    public function description($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  class-string<PipeRule>|Closure  $pipe
     *
     * @throws PipeHubException when the pipe is neither a PipeRule class nor a closure
     */
    public function addPipe($pipe)
    {
        $isRule = is_string($pipe) && class_exists($pipe) && is_subclass_of($pipe, PipeRule::class);

        if (! $isRule && ! $pipe instanceof Closure) {
            throw PipeHubException::invalidPipe($pipe);
        }

        $this->pipes[] = $pipe;

        return $this;
    }

    public function addPipes(array $pipes)
    {
        foreach ($pipes as $pipe) {
            $this->addPipe($pipe);
        }

        return $this;
    }

    /**
     * When the callback returns true the hub still runs, but every rule in
     * it is logged as skipped and none executes. then() still runs.
     */
    public function skipIf(Closure $callback)
    {
        $this->skipIfCallback = $callback;

        return $this;
    }

    /**
     * Skip this hub entirely: nothing in it runs, not even then().
     */
    public function skip($shouldSkip = true)
    {
        $this->skipThisHub = (bool) $shouldSkip;

        return $this;
    }

    public function subject($subject)
    {
        $this->subject = PipeEnvelope::wrap($subject, $this->app);

        return $this;
    }

    /**
     * Runs after the hub's last rule, when no fatal stopped the hub.
     */
    public function then(Closure $callback)
    {
        $this->thenCallback = $callback;

        return $this;
    }

    /**
     * Runs for every error() and unexpected exception a pipe in this hub
     * records, with fn ($subject, $exception); then the next pipe runs.
     * Errors that count as fatals (errorsAreFatal()) go to onFatal() instead.
     */
    public function onError(Closure $callback)
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Runs when a fatal stops this hub, with fn ($subject, $exception), the
     * exception being the PipeRuleFatal; whether the fatal ends just this
     * hub or the whole flow. It runs before the flow moves on, returns or
     * throws. A redirect or view thrown from it is handed off as usual.
     */
    public function onFatal(Closure $callback)
    {
        $this->onFatal = $callback;

        return $this;
    }

    /**
     * Inside a PipeManager, a fatal in this hub ends only this hub: the
     * manager moves on to the next hub (and its then() still runs). Without
     * it a fatal ends the whole flow. A hub run on its own is the whole
     * flow, so this changes nothing there.
     */
    public function fatalsStopThisHubOnly($only = true)
    {
        $this->fatalsStopThisHubOnly = (bool) $only;

        return $this;
    }

    /**
     * Every error() and every unexpected exception in this hub's pipes
     * counts as a fatal (recorded as FATAL_FAIL, sent to onFatal(), not
     * onError()). How far it reaches is then up to fatalsStopThisHubOnly().
     */
    public function errorsAreFatal($fatal = true)
    {
        $this->errorsAreFatal = (bool) $fatal;

        return $this;
    }

    /**
     * For a hub run on its own with sendIt(): rethrow a fatal out of
     * sendIt(), so a try/catch around the run catches it. Off by default:
     * sendIt() returns the envelope and the fatal is only recorded on it.
     *
     * Everything else still happens first: the fatal is logged, onFatal()
     * runs, then() does not run and the log is persisted (see persistLogs()).
     * The same PipeRuleFatal is thrown, so subject() is the envelope.
     *
     * Inside a PipeManager this setting is ignored: set throwFatals() on
     * the manager instead.
     */
    public function throwFatals($shouldThrow = true)
    {
        $this->throwFatals = (bool) $shouldThrow;

        return $this;
    }

    /**
     * Whether a hub run on its own persists its log when it finishes. Hubs
     * inside a PipeManager never persist; the manager persists once.
     */
    public function persistLogs($persist = true)
    {
        $this->persistLogsOnFinalize = (bool) $persist;

        return $this;
    }

    public function broadcastEventBefore($event)
    {
        return $this->registerEvent('before', $event);
    }

    public function broadcastEventAfter($event)
    {
        return $this->registerEvent('after', $event);
    }

    public function broadcastEventOnSkip($event)
    {
        return $this->registerEvent('skip', $event);
    }

    public function broadcastEventBeforeEachPipe($event)
    {
        return $this->registerEvent('beforeEach', $event);
    }

    public function broadcastEventAfterEachPipe($event)
    {
        return $this->registerEvent('afterEach', $event);
    }

    protected function registerEvent(string $type, $event)
    {
        if (! is_string($event) || ! class_exists($event)) {
            throw PipeHubException::invalidEvent($event);
        }

        $this->broadcastEvents[$type] = $event;

        return $this;
    }

    /**
     * Run this hub on its own: the hub is the whole flow, so its log is
     * persisted when it finishes (see persistLogs()). By default a fatal
     * does not escape; read it from the envelope with hasFatalErrors().
     *
     * @throws PipeRuleFatal with throwFatals(), when a fatal stopped the run
     */
    public function sendIt($subject = null)
    {
        $subject = $this->prepareSubject($subject);

        if ($subject->flowName() === null) {
            $subject->setFlowName($this->name);
        }

        try {
            return $this->run($subject);
        } catch (PipeRuleFatal $e) {
            if ($this->throwFatals) {
                // The finally below persists the log before this leaves.
                throw $e->withSubject($subject);
            }

            return $subject;
        } catch (PipeRuleRedirect $e) {
            // Defensive: whatever threw it, the caller gets a usable URL.
            throw $e->resolveRoute($this->app);
        } finally {
            if ($this->persistLogsOnFinalize) {
                $subject->persist();
            }
        }
    }

    /**
     * Run this hub as one stage of a PipeManager flow: nothing is persisted,
     * every error is also reported to $onFlowError (the manager's onError()),
     * and a fatal that ends the flow is rethrown to the manager.
     *
     * @internal
     *
     * @throws PipeRuleFatal when the flow must stop
     */
    public function sendItWithinFlow(PipeEnvelope $subject, ?Closure $onFlowError = null): PipeEnvelope
    {
        return $this->run($this->prepareSubject($subject), true, $onFlowError);
    }

    protected function prepareSubject($subject): PipeEnvelope
    {
        // Only null is missing: false, 0, '' and [] are subjects like any other.
        $subject = $subject ?? $this->subject;

        if ($subject === null) {
            throw PipeHubException::missingSubject();
        }

        return $this->subject = PipeEnvelope::wrap($subject, $this->app);
    }

    /**
     * Everything here works on the $subject it was given, never on
     * $this->subject: a rule may run this same hub again for another
     * subject while this run is still going.
     *
     * @param  bool  $withinFlow  run by a PipeManager (only then can a fatal
     *                            be contained to this hub)
     */
    protected function run(PipeEnvelope $subject, bool $withinFlow = false, ?Closure $onFlowError = null): PipeEnvelope
    {
        if ($this->skipThisHub) {
            $this->log($subject, 'info', 'PipeHub:sendIt', 'Skipping hub', $this->getPipeHubContext());
            $this->sendEvent($subject, 'skip');

            return $subject;
        }

        $pipes = [];

        try {
            $skipRules = (bool) ($this->skipIfCallback
                ? $this->callViaContainer($this->skipIfCallback, ['subject' => $subject])
                : false);

            $onError = $this->errorReporter($subject, $onFlowError);

            $pipes = $this->instantiatedPipes = array_map(
                fn ($pipe) => $this->instantiate($pipe, $skipRules, $onError),
                $this->pipes
            );

            $this->log($subject, 'info', 'PipeHub:sendIt', 'Sending subject through all pipes', $this->getPipeHubContext());

            if ($skipRules) {
                $this->log($subject, 'info', 'PipeHub:sendIt', 'Skipping every rule: skipIf() returned true', $this->getPipeHubContext());
                $this->sendEvent($subject, 'skip');
            } else {
                $this->sendEvent($subject, 'before');
            }

            (new Pipeline($this->app))
                ->send($subject)
                ->through($pipes)
                ->hooks(
                    function ($passable, $pipe) use ($subject) {
                        if (! $this->pipeWillSkip($passable, $pipe)) {
                            $this->sendEvent($subject, 'beforeEach', $pipe);
                        }
                    },
                    function ($passable, $pipe) use ($subject) {
                        if (! ($pipe instanceof PipeRule && $pipe->wasSkipped())) {
                            $this->sendEvent($subject, 'afterEach', $pipe);
                        }
                    }
                )
                ->then(function () use ($subject, $skipRules, $onError) {
                    if (! $skipRules) {
                        $this->sendEvent($subject, 'after');
                    }

                    return $this->executeThenCallback($subject, $onError);
                });
        } catch (PipeRuleFatal $e) {
            $this->stopForFatal($e, $subject, $withinFlow);
        } catch (PipeRuleRedirect $e) {
            $e->resolveRoute($this->app);
            $this->log($subject, 'info', 'PipeHub:sendIt', 'Redirecting to '.$e->redirectTo);

            throw $e;
        } catch (PipeRuleLoadView $e) {
            $this->log($subject, 'info', 'PipeHub:sendIt', 'Loading view '.$e->view);

            throw $e;
        } catch (PipeHubException $e) {
            // A configuration error: never treated as a failure of this run.
            $this->log($subject, 'error', 'PipeHub:sendIt', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeHubContext());

            throw $e;
        } catch (Throwable $e) {
            // Something outside the pipes failed (skipIf(), an event
            // listener, an onError() hook): the hub cannot carry on, so it
            // stops as for a fatal, wrapping the exception.
            $subject->logger()->fatal('PipeHub:sendIt', 'The hub cannot carry on: '.$e->getMessage(), ['exception' => get_class($e)] + $this->getPipeHubContext());

            $this->stopForFatal(new PipeRuleFatal($e->getMessage(), $subject, 0, $e), $subject, $withinFlow);
        } finally {
            $this->log($subject, 'info', 'PipeHub:sendIt', 'Finalized result!');

            // A nested run of this hub may have replaced these; the run that
            // finishes last is the one getSubject() describes.
            $this->subject = $subject;
            $this->instantiatedPipes = $pipes;
        }

        return $subject;
    }

    /**
     * Log the stop, run onFatal(), then rethrow the fatal unless it is
     * contained to this hub (fatalsStopThisHubOnly() inside a manager).
     *
     * @throws PipeRuleFatal when the fatal ends the flow
     */
    protected function stopForFatal(PipeRuleFatal $e, PipeEnvelope $subject, bool $withinFlow): void
    {
        $ends = $withinFlow && $this->fatalsStopThisHubOnly ? 'hub' : 'flow';

        $this->log($subject, 'warning', 'PipeHub:sendIt', sprintf(
            'Stopped by a fatal: %s. The rest of this %s does not run.',
            $e->getMessage(),
            $ends
        ), $this->getPipeHubContext() + ['ends' => $ends]);

        if ($this->onFatal) {
            try {
                $this->callViaContainer($this->onFatal, ['subject' => $subject, 'exception' => $e]);
            } catch (PipeRuleRedirect $redirect) {
                // Thrown from inside a catch block, so run()'s redirect
                // catch never sees it: resolve and log it here.
                $redirect->resolveRoute($this->app);
                $this->log($subject, 'info', 'PipeHub:sendIt', 'Redirecting to '.$redirect->redirectTo);

                throw $redirect;
            } catch (PipeRuleLoadView $view) {
                $this->log($subject, 'info', 'PipeHub:sendIt', 'Loading view '.$view->view);

                throw $view;
            } catch (PipeHubException $configurationError) {
                throw $configurationError;
            } catch (Throwable $hookFailure) {
                // The hub is already stopping: record the hook's own failure
                // and carry on stopping exactly as planned.
                $subject->logger()->fatal('PipeHub:onFatal', 'onFatal() failed: '.$hookFailure->getMessage(), ['exception' => get_class($hookFailure)] + $this->getPipeHubContext());
            }
        }

        if ($ends === 'flow') {
            throw $e;
        }
    }

    /**
     * What a pipe (or then()) calls for each error it records and carries
     * on past: this hub's onError(), then the manager's.
     */
    protected function errorReporter(PipeEnvelope $subject, ?Closure $onFlowError): ?Closure
    {
        if (! $this->onError && ! $onFlowError) {
            return null;
        }

        return function (Throwable $e) use ($subject, $onFlowError) {
            try {
                if ($this->onError) {
                    $this->callViaContainer($this->onError, ['subject' => $subject, 'exception' => $e]);
                }

                if ($onFlowError) {
                    $onFlowError($e);
                }
            } catch (PipeRuleFatal $fatal) {
                // A hook may escalate an error by throwing a fatal: record it
                // like any other, so hasFatalErrors() reports it.
                $subject->logger()->fatal('PipeHub:onError', $fatal->getMessage(), ['exception' => get_class($fatal)] + $this->getPipeHubContext());

                throw $fatal;
            }
        };
    }

    protected function instantiate($pipe, bool $skip, ?Closure $onError = null): PipeRule
    {
        if ($pipe instanceof Closure) {
            $rule = new ClosurePipe($pipe);
        } else {
            $rule = $this->app->make($pipe);

            // A rule the container shares (singleton, instance, scoped) is
            // run as a copy, so the shared object is never executed or
            // changed and nested runs never share a subject, policy or
            // container. A transient resolution is already this run's own
            // object and runs as it is.
            if ($this->isSharedBinding($pipe)) {
                $rule = $this->copyOfSharedRule($rule);
            }
        }

        // The policy is written in full on every run either way.
        return $rule
            ->applyPolicy($skip, 'Skipped: the hub\'s skipIf() returned true', $this->errorsAreFatal, $onError)
            ->setContainer($this->app);
    }

    protected function isSharedBinding(string $pipe): bool
    {
        // Illuminate's container (Laravel 9 to 13) answers this. Any other
        // container cannot tell us, so assume shared and copy, the safe side.
        if (! method_exists($this->app, 'isShared')) {
            return true;
        }

        $abstract = method_exists($this->app, 'getAlias') ? $this->app->getAlias($pipe) : $pipe;

        return (bool) $this->app->isShared($abstract);
    }

    protected function copyOfSharedRule(PipeRule $rule): PipeRule
    {
        if (! (new \ReflectionClass($rule))->isCloneable()) {
            throw PipeHubException::sharedRuleNotCloneable(get_class($rule));
        }

        try {
            return clone $rule;
        } catch (Throwable $e) {
            throw PipeHubException::sharedRuleNotCloneable(get_class($rule), $e);
        }
    }

    protected function pipeWillSkip($subject, $pipe): bool
    {
        return $pipe instanceof PipeRule && $pipe->willSkip($subject);
    }

    protected function sendEvent(PipeEnvelope $subject, string $eventType, $pipe = null)
    {
        if (! $event = $this->broadcastEvents[$eventType] ?? null) {
            return;
        }

        $pipeKey = $pipe instanceof PipeRule ? $pipe->ruleKey() : null;

        $this->log($subject, 'info', 'PipeHub:event', "Broadcasting [{$eventType}] event {$event}", array_filter([
            'event' => $eventType,
            'hub' => $this->name,
            'pipe' => $pipeKey,
        ]));

        $this->app->make('events')->dispatch(
            $pipeKey === null ? new $event($subject) : new $event($subject, $pipeKey)
        );
    }

    /**
     * then() is handled like a pipe: a fatal thrown there stops as a fatal,
     * any other exception is an error (recorded, reported to onError(), and
     * the flow carries on), or a fatal under errorsAreFatal().
     */
    protected function executeThenCallback(PipeEnvelope $subject, ?Closure $onError)
    {
        if (! $this->thenCallback) {
            return $subject;
        }

        try {
            $this->callViaContainer($this->thenCallback, ['subject' => $subject]);
        } catch (PipeRuleRedirect|PipeRuleLoadView|PipeHubException $e) {
            throw $e;
        } catch (PipeRuleFatal $e) {
            $subject->logger()->fatal('PipeHub:executeThenCallback', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeHubContext());

            throw $e;
        } catch (Throwable $e) {
            $context = ['exception' => get_class($e)] + $this->getPipeHubContext();

            if ($this->errorsAreFatal) {
                $subject->logger()->fatal('PipeHub:executeThenCallback', $e->getMessage(), $context);

                throw new PipeRuleFatal($e->getMessage(), $subject, 0, $e);
            }

            $subject->logger()->error('PipeHub:executeThenCallback', $e->getMessage(), $context);

            if ($onError) {
                $onError($e);
            }
        }

        return $subject;
    }

    protected function callViaContainer($callback, $parameters = [])
    {
        return $this->app->call($callback, $this->withSubjectParameter($callback, $parameters));
    }

    protected function log(PipeEnvelope $subject, string $level, $key, $message, $context = [])
    {
        $subject->logger()->{$level}($key, $message, $context);
    }

    protected function getPipeHubContext()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function wrapInEnvelope($subject)
    {
        return PipeEnvelope::wrap($subject, $this->app);
    }

    public function getInstantiatedPipes(): array
    {
        return $this->instantiatedPipes;
    }

    public function getPipes(): array
    {
        return $this->pipes;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSubject(): ?PipeEnvelope
    {
        return $this->subject;
    }

    public function getSkipIf(): ?Closure
    {
        return $this->skipIfCallback;
    }

    public function getThen(): ?Closure
    {
        return $this->thenCallback;
    }

    public function getOnError(): ?Closure
    {
        return $this->onError;
    }

    public function getOnFatal(): ?Closure
    {
        return $this->onFatal;
    }

    /** @return array<string, class-string|null> */
    public function getEvents(): array
    {
        return $this->broadcastEvents;
    }

    public function stopsOnlyThisHubOnFatal(): bool
    {
        return $this->fatalsStopThisHubOnly;
    }

    public function treatsErrorsAsFatal(): bool
    {
        return $this->errorsAreFatal;
    }

    public function isSkipped(): bool
    {
        return $this->skipThisHub;
    }

    public function persistsLogs(): bool
    {
        return $this->persistLogsOnFinalize;
    }

    public function throwsFatals(): bool
    {
        return $this->throwFatals;
    }
}
