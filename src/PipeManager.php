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
 * A whole flow: hubs run in order against one envelope. The log is
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

    protected bool $persistLogsOnFinalize = true;

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
     * Runs after the last hub, unless a fatal ended the flow early.
     */
    public function then(Closure $callback)
    {
        $this->thenCallback = $callback;

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

    public function sendIt()
    {
        if ($this->subject === null) {
            throw PipeHubException::missingSubject();
        }

        $subject = $this->subject = PipeEnvelope::wrap($this->subject);

        if ($subject->flowName() === null) {
            $subject->setFlowName($this->name);
        }

        $subject->logger()->info('PipeManager:sendIt', 'Sending subject through all hubs', $this->getPipeManagerContext());

        try {
            $completed = false;

            try {
                foreach ($this->hubs as $hub) {
                    $subject = $this->subject = $hub->sendItWithinFlow($subject);
                }

                $completed = true;
            } catch (PipeRuleRedirect|PipeRuleLoadView $e) {
                $subject->logger()->info('PipeManager:sendIt', 'Flow handed off: '.$e->getMessage());

                throw $e;
            } catch (PipeRuleFatal $e) {
                $subject->logger()->warning('PipeManager:sendIt', 'Flow stopped by a fatal: '.$e->getMessage(), $this->getPipeManagerContext());
            } catch (Throwable $e) {
                $subject->logger()->error('PipeManager:sendIt', $e->getMessage(), ['exception' => get_class($e)] + $this->getPipeManagerContext());
            }

            if ($completed) {
                $this->callback($this->thenCallback, ['subject' => $subject]);
            }

            $subject->logger()->info('PipeManager:sendIt', 'Finalized result!');
        } finally {
            if ($this->persistLogsOnFinalize) {
                $subject->persist();
            }
        }

        return $subject;
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

    public function persistsLogs(): bool
    {
        return $this->persistLogsOnFinalize;
    }
}
