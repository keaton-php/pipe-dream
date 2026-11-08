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

/**
 * A whole flow: hubs run in order against one envelope. A fatal in any hub
 * stops the flow (unless that hub uses fatalsStopThisHubOnly()). The log is
 * persisted once, when the flow ends, whether it completed or not.
 */
class PipeManager
{
    use PassesSubjectToCallbacks;

    protected array $hubs = [];

    protected $subject;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?Closure $thenCallback = null;

    protected ?Closure $onError = null;

    protected ?Closure $onFatal = null;

    protected bool $persistLogsOnFinalize = true;

    protected bool $throwFatals = false;

    protected $app;

    public function __construct(?Container $app = null)
    {
        $this->app = $app ?? IlluminateContainer::getInstance();
    }

    /**
     * @param  array<PipeHub>  $hubs
     */
    public static function create(array $hubs = [], ?Container $app = null)
    {
        return (new static($app))->addHubs($hubs);
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
     * @throws PipeHubException when the hub is not a PipeHub
     */
    public function addHub($hub)
    {
        if (! $hub instanceof PipeHub) {
            throw PipeHubException::invalidHub($hub);
        }

        $this->hubs[] = $hub;

        return $this;
    }

    public function addHubs(array $hubs)
    {
        foreach ($hubs as $hub) {
            $this->addHub($hub);
        }

        return $this;
    }

    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Runs after the last hub, unless a fatal stopped the flow. A fatal
     * contained to its hub (fatalsStopThisHubOnly()) does not stop it.
     */
    public function then(Closure $callback)
    {
        $this->thenCallback = $callback;

        return $this;
    }

    /**
     * Runs for every error() and unexpected exception recorded by a pipe in
     * any hub, with fn ($subject, $exception), after that hub's own
     * onError(); then the flow carries on.
     */
    public function onError(Closure $callback)
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Runs when a fatal stops the flow, with fn ($subject, $exception), the
     * exception being the PipeRuleFatal, after the hub's own onFatal() and
     * before sendIt() returns or throws. Not for a fatal contained to its
     * hub. A redirect or view thrown from it is handed off as usual.
     */
    public function onFatal(Closure $callback)
    {
        $this->onFatal = $callback;

        return $this;
    }

    /**
     * Persist the log once when the flow ends (the default).
     */
    public function persistLogs($persist = true)
    {
        $this->persistLogsOnFinalize = (bool) $persist;

        return $this;
    }

    /**
     * Rethrow the fatal that stopped the flow out of sendIt(), so a
     * try/catch around the flow catches it. Off by default: sendIt()
     * returns the envelope and the fatal is only recorded on it.
     *
     * A fatal contained to its hub (fatalsStopThisHubOnly()) lets the flow
     * carry on and is never thrown. Everything else still happens first:
     * the fatal is logged, onFatal() runs, then() does not run and the log is
     * persisted. The same PipeRuleFatal is thrown, so subject() is the
     * envelope (its log included) and getPrevious() is the error or
     * exception behind a fatal raised by errorsAreFatal().
     */
    public function throwFatals($shouldThrow = true)
    {
        $this->throwFatals = (bool) $shouldThrow;

        return $this;
    }

    /**
     * @throws PipeRuleFatal with throwFatals(), when a fatal stopped the flow
     */
    public function sendIt()
    {
        if ($this->subject === null) {
            throw PipeHubException::missingSubject();
        }

        $subject = $this->subject = PipeEnvelope::wrap($this->subject, $this->app);

        if ($subject->flowName() === null) {
            $subject->setFlowName($this->name);
        }

        $subject->logger()->info('PipeManager:sendIt', 'Sending subject through all hubs', $this->getPipeManagerContext());

        $fatal = null;

        $onFlowError = $this->onError
            ? fn (Throwable $e) => $this->callViaContainer($this->onError, ['subject' => $subject, 'exception' => $e])
            : null;

        try {
            $completed = false;

            try {
                foreach ($this->hubs as $hub) {
                    $subject = $this->subject = $hub->sendItWithinFlow($subject, $onFlowError);
                }

                $completed = true;
            } catch (PipeRuleRedirect|PipeRuleLoadView $e) {
                if ($e instanceof PipeRuleRedirect) {
                    // Defensive: hubs resolve their own redirects, but nothing
                    // leaves the manager without a URL.
                    $e->resolveRoute($this->app);
                }

                $subject->logger()->info('PipeManager:sendIt', 'Flow handed off: '.$e->getMessage());

                throw $e;
            } catch (PipeHubException $e) {
                // A configuration error is the caller's to fix, not a flow outcome.
                $subject->logger()->error('PipeManager:sendIt', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeManagerContext());

                throw $e;
            } catch (PipeRuleFatal $e) {
                $subject->logger()->warning('PipeManager:sendIt', 'Flow stopped by a fatal: '.$e->getMessage(), $this->getPipeManagerContext());

                $fatal = $e;
            } catch (Throwable $e) {
                // Anything a hub let through that is not a hand-off, a
                // configuration error or a fatal: the flow cannot carry on.
                $subject->logger()->fatal('PipeManager:sendIt', 'The flow cannot carry on: '.$e->getMessage(), ['exception' => get_class($e)] + $this->getPipeManagerContext());

                $fatal = new PipeRuleFatal($e->getMessage(), $subject, 0, $e);
                $subject->markFatalRecorded($fatal);
            }

            if ($completed) {
                $fatal = $this->runThen($subject, $onFlowError);
            }

            if ($fatal) {
                $this->runOnFatal($subject, $fatal);
            }

            $subject->logger()->info('PipeManager:sendIt', 'Finalized result!');
        } finally {
            if ($this->persistLogsOnFinalize) {
                $subject->persist();
            }
        }

        if ($fatal && $this->throwFatals) {
            throw $fatal->withSubject($subject);
        }

        return $subject;
    }

    /**
     * then() is handled like a pipe: a fatal thrown there stops the flow as
     * a fatal; any other exception is an error (recorded, reported to
     * onError()) and the flow finishes as usual.
     *
     * @return PipeRuleFatal|null the fatal, when then() stopped the flow
     */
    protected function runThen(PipeEnvelope $subject, ?Closure $onFlowError): ?PipeRuleFatal
    {
        try {
            $this->callback($this->thenCallback, ['subject' => $subject]);
        } catch (PipeRuleRedirect $e) {
            throw $e->resolveRoute($this->app);
        } catch (PipeRuleLoadView|PipeHubException $e) {
            throw $e;
        } catch (PipeRuleFatal $e) {
            $subject->markFatalRecorded($e);
            $subject->logger()->fatal('PipeManager:then', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeManagerContext());
            $subject->logger()->warning('PipeManager:sendIt', 'Flow stopped by a fatal: '.$e->getMessage(), $this->getPipeManagerContext());

            return $e;
        } catch (Throwable $e) {
            $subject->logger()->error('PipeManager:then', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeManagerContext());

            if ($onFlowError) {
                try {
                    $onFlowError($e);
                } catch (PipeRuleRedirect $redirect) {
                    throw $redirect->resolveRoute($this->app);
                } catch (PipeRuleLoadView|PipeHubException $handoff) {
                    throw $handoff;
                } catch (Throwable $hookFailure) {
                    // The error hook itself failed: the flow cannot finish cleanly.
                    $subject->logger()->fatal('PipeManager:sendIt', 'The flow cannot carry on: '.$hookFailure->getMessage(), ['exception' => get_class($hookFailure)] + $this->getPipeManagerContext());

                    $fatal = $hookFailure instanceof PipeRuleFatal
                        ? $hookFailure
                        : new PipeRuleFatal($hookFailure->getMessage(), $subject, 0, $hookFailure);
                    $subject->markFatalRecorded($fatal);

                    return $fatal;
                }
            }
        }

        return null;
    }

    /**
     * Run onFatal() for a fatal that stopped the flow. A redirect or view
     * from it is handed off; any other exception from it is recorded and
     * the stop carries on as planned.
     */
    protected function runOnFatal(PipeEnvelope $subject, PipeRuleFatal $fatal): void
    {
        if (! $this->onFatal) {
            return;
        }

        try {
            $this->callViaContainer($this->onFatal, ['subject' => $subject, 'exception' => $fatal]);
        } catch (PipeRuleRedirect|PipeRuleLoadView $e) {
            if ($e instanceof PipeRuleRedirect) {
                $e->resolveRoute($this->app);
            }

            $subject->logger()->info('PipeManager:sendIt', 'Flow handed off: '.$e->getMessage());

            throw $e;
        } catch (PipeHubException $configurationError) {
            throw $configurationError;
        } catch (Throwable $hookFailure) {
            $subject->markFatalRecorded($hookFailure);
            $subject->logger()->fatal('PipeManager:onFatal', 'onFatal() failed: '.$hookFailure->getMessage(), ['exception' => get_class($hookFailure)] + $this->getPipeManagerContext());
        }
    }

    public function callback(?Closure $callback = null, $parameters = [])
    {
        return $callback ? $this->callViaContainer($callback, $parameters) : null;
    }

    protected function callViaContainer($callback, $parameters = [])
    {
        return $this->app->call($callback, $this->withSubjectParameter($callback, $parameters));
    }

    protected function getPipeManagerContext()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    /** @return array<PipeHub> */
    public function hubs()
    {
        return $this->hubs;
    }

    /** @return array<PipeHub> */
    public function getHubs(): array
    {
        return $this->hubs;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
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

    public function persistsLogs(): bool
    {
        return $this->persistLogsOnFinalize;
    }

    public function throwsFatals(): bool
    {
        return $this->throwFatals;
    }
}
