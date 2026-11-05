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
use Throwable;
use Keaton\PipeDream\PipeDreamPipeline as Pipeline;

/**
 * A named stage of a flow: an ordered list of rules (or closures) the
 * subject passes through, with per-stage control over skipping and
 * stopping, a then() callback and events.
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

    protected ?Closure $onFailure = null;

    protected bool $stopOnError = false;

    protected bool $stopOnFatal = false;

    protected bool $failureIsFatal = false;

    protected bool $persistLogsOnFinalize = true;

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
        $this->subject = PipeEnvelope::wrap($subject);

        return $this;
    }

    /**
     * Runs after the hub's last rule, when the hub was not ended by a fatal
     * or a failure.
     */
    public function then(Closure $callback)
    {
        $this->thenCallback = $callback;

        return $this;
    }

    /**
     * Runs when the hub fails: an exception escaped the pipeline (an error
     * under stopOnError(), or a rule using withoutErrorHandling()).
     */
    public function onFailure(Closure $callback)
    {
        $this->onFailure = $callback;

        return $this;
    }

    /**
     * Without this, a fatal ends this hub and the flow moves on to the next
     * hub. With it, a fatal here ends the whole flow.
     */
    public function stopOnFatal($shouldStopOnFatal = true)
    {
        $this->stopOnFatal = (bool) $shouldStopOnFatal;

        return $this;
    }

    /**
     * A PipeRuleError ends this hub (as a failure) instead of being logged
     * and passed over.
     */
    public function stopOnError($shouldStopOnError = true)
    {
        $this->stopOnError = (bool) $shouldStopOnError;

        return $this;
    }

    /**
     * A failure in this hub is logged as a fatal and ends the whole flow.
     * onFailure() still runs first.
     */
    public function failureIsFatal($isFatal = true)
    {
        $this->failureIsFatal = (bool) $isFatal;

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
     * persisted when it finishes (see persistLogs()). A fatal never escapes;
     * read it from the envelope with hasFatalErrors().
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
            return $subject;
        } finally {
            if ($this->persistLogsOnFinalize) {
                $subject->persist();
            }
        }
    }

    /**
     * Run this hub as one stage of a PipeManager flow: nothing is persisted,
     * and a fatal that must end the flow is rethrown to the manager.
     *
     * @internal
     *
     * @throws PipeRuleFatal when the flow must stop
     */
    public function sendItWithinFlow(PipeEnvelope $subject): PipeEnvelope
    {
        return $this->run($this->prepareSubject($subject));
    }

    protected function prepareSubject($subject): PipeEnvelope
    {
        if (! $subject = $subject ?? $this->subject) {
            throw PipeHubException::missingSubject();
        }

        return $this->subject = PipeEnvelope::wrap($subject);
    }

    protected function run(PipeEnvelope $subject): PipeEnvelope
    {
        if ($this->skipThisHub) {
            $this->logInfo('PipeHub:sendIt', 'Skipping hub', $this->getPipeHubContext());
            $this->sendEvent('skip');

            return $subject;
        }

        try {
            $skipRules = (bool) ($this->skipIfCallback
                ? $this->callViaContainer($this->skipIfCallback, ['subject' => $subject])
                : false);

            $this->instantiatedPipes = array_map(
                fn ($pipe) => $this->instantiate($pipe, $skipRules),
                $this->pipes
            );

            $this->logInfo('PipeHub:sendIt', 'Sending subject through all pipes', $this->getPipeHubContext());

            if ($skipRules) {
                $this->logInfo('PipeHub:sendIt', 'Skipping every rule: skipIf() returned true', $this->getPipeHubContext());
                $this->sendEvent('skip');
            } else {
                $this->sendEvent('before');
            }

            (new Pipeline($this->app))
                ->send($subject)
                ->through($this->instantiatedPipes)
                ->hooks(
                    function ($passable, $pipe) {
                        if (! $this->pipeWillSkip($passable, $pipe)) {
                            $this->sendEvent('beforeEach', $pipe);
                        }
                    },
                    function ($passable, $pipe) {
                        if (! ($pipe instanceof PipeRule && $pipe->wasSkipped())) {
                            $this->sendEvent('afterEach', $pipe);
                        }
                    }
                )
                ->then(function ($subject) use ($skipRules) {
                    if (! $skipRules) {
                        $this->sendEvent('after');
                    }

                    return $this->executeThenCallback($subject);
                });
        } catch (PipeRuleFatal $e) {
            $this->logWarning('PipeHub:sendIt', sprintf(
                'Stopped by a fatal: %s. The rest of this %s does not run.',
                $e->getMessage(),
                $this->stopOnFatal ? 'flow' : 'hub'
            ), $this->getPipeHubContext() + ['ends' => $this->stopOnFatal ? 'flow' : 'hub']);

            if ($this->stopOnFatal) {
                throw $e;
            }
        } catch (PipeRuleRedirect $e) {
            $this->logInfo('PipeHub:sendIt', 'Redirecting to '.$e->redirectTo);

            throw $e;
        } catch (PipeRuleLoadView $e) {
            $this->logInfo('PipeHub:sendIt', 'Loading view '.$e->view);

            throw $e;
        } catch (Throwable $e) {
            $this->logError('PipeHub:sendIt', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeHubContext());

            if ($this->onFailure) {
                $this->callViaContainer($this->onFailure, ['subject' => $subject, 'exception' => $e]);
            }

            if ($this->failureIsFatal) {
                $subject->logger()->fatal('PipeHub:sendIt', 'Failure is fatal: '.$e->getMessage(), $this->getPipeHubContext() + ['ends' => 'flow']);

                throw new PipeRuleFatal($e->getMessage(), $subject, 0, $e);
            }
        } finally {
            $this->logInfo('PipeHub:sendIt', 'Finalized result!');
        }

        return $subject;
    }

    protected function instantiate($pipe, bool $skip): PipeRule
    {
        $rule = $pipe instanceof Closure ? new ClosurePipe($pipe) : $this->app->make($pipe);

        if ($this->stopOnError) {
            $rule->setStopOnError(true);
        }

        if ($skip) {
            $rule->shouldSkip(true, 'Skipped: the hub\'s skipIf() returned true');
        }

        return $rule;
    }

    protected function pipeWillSkip($subject, $pipe): bool
    {
        return $pipe instanceof PipeRule && $pipe->willSkip($subject);
    }

    protected function sendEvent(string $eventType, $pipe = null)
    {
        if (! $event = $this->broadcastEvents[$eventType] ?? null) {
            return;
        }

        $pipeKey = $pipe instanceof PipeRule ? $pipe->ruleKey() : null;

        $this->logInfo('PipeHub:event', "Broadcasting [{$eventType}] event {$event}", array_filter([
            'event' => $eventType,
            'hub' => $this->name,
            'pipe' => $pipeKey,
        ]));

        $this->app->make('events')->dispatch(
            $pipeKey === null ? new $event($this->subject) : new $event($this->subject, $pipeKey)
        );
    }

    protected function executeThenCallback($subject)
    {
        try {
            if ($this->thenCallback) {
                $this->callViaContainer($this->thenCallback, ['subject' => $subject]);
            }
        } catch (PipeRuleRedirect|PipeRuleLoadView $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logError('PipeHub:executeThenCallback', $e->getMessage(), ['exception' => get_class($e)]);
        }

        return $subject;
    }

    protected function callViaContainer($callback, $parameters = [])
    {
        return $this->app->call($callback, $this->withSubjectParameter($callback, $parameters));
    }

    protected function logInfo($key, $message, $context = [])
    {
        $this->subject->logger()->info($key, $message, $context);
    }

    protected function logWarning($key, $message, $context = [])
    {
        $this->subject->logger()->warning($key, $message, $context);
    }

    protected function logError($key, $message, $context = [])
    {
        $this->subject->logger()->error($key, $message, $context);
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
        return PipeEnvelope::wrap($subject);
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

    public function getOnFailure(): ?Closure
    {
        return $this->onFailure;
    }

    /** @return array<string, class-string|null> */
    public function getEvents(): array
    {
        return $this->broadcastEvents;
    }

    public function stopsOnFatal(): bool
    {
        return $this->stopOnFatal;
    }

    public function stopsOnError(): bool
    {
        return $this->stopOnError;
    }

    public function isFailureFatal(): bool
    {
        return $this->failureIsFatal;
    }

    public function isSkipped(): bool
    {
        return $this->skipThisHub;
    }

    public function persistsLogs(): bool
    {
        return $this->persistLogsOnFinalize;
    }
}
