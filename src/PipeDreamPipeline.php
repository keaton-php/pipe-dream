<?php

namespace Keaton\PipeDream;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pipeline\Pipeline as PipelineContract;
use RuntimeException;

/**
 * Laravel's pipeline, plus two hooks: one fired just before each pipe runs,
 * and one fired the moment a pipe hands off to the next (it calls $next).
 * A pipe that throws never hands off, so its after hook does not fire.
 *
 * @internal Used by PipeHub.
 */
class PipeDreamPipeline implements PipelineContract
{
    protected ?Container $container;

    protected $passable;

    protected array $pipes = [];

    protected string $method = 'handle';

    protected ?Closure $beforeEach = null;

    protected ?Closure $afterEach = null;

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    public function send($passable)
    {
        $this->passable = $passable;

        return $this;
    }

    public function through($pipes)
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();

        return $this;
    }

    public function pipe($pipes)
    {
        array_push($this->pipes, ...(is_array($pipes) ? $pipes : func_get_args()));

        return $this;
    }

    public function via($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param  Closure(mixed $passable, mixed $pipe): void|null  $before
     * @param  Closure(mixed $passable, mixed $pipe): void|null  $after
     */
    public function hooks(?Closure $before = null, ?Closure $after = null)
    {
        $this->beforeEach = $before;
        $this->afterEach = $after;

        return $this;
    }

    public function then(Closure $destination)
    {
        $pipeline = array_reduce(array_reverse($this->pipes), $this->carry(), $destination);

        return $pipeline($this->passable);
    }

    public function thenReturn()
    {
        return $this->then(fn ($passable) => $passable);
    }

    protected function carry()
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if ($this->beforeEach) {
                    ($this->beforeEach)($passable, $pipe);
                }

                $next = $this->afterEach
                    ? function ($carried) use ($stack, $pipe) {
                        ($this->afterEach)($carried, $pipe);

                        return $stack($carried);
                    }
                    : $stack;

                if ($pipe instanceof Closure) {
                    return $pipe($passable, $next);
                }

                if (! is_object($pipe)) {
                    [$name, $parameters] = $this->parsePipeString($pipe);
                    $pipe = $this->getContainer()->make($name);
                    $parameters = array_merge([$passable, $next], $parameters);
                } else {
                    $parameters = [$passable, $next];
                }

                return method_exists($pipe, $this->method)
                    ? $pipe->{$this->method}(...$parameters)
                    : $pipe(...$parameters);
            };
        };
    }

    protected function parsePipeString($pipe)
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    protected function getContainer()
    {
        if (! $this->container) {
            throw new RuntimeException('A container instance has not been passed to the Pipeline.');
        }

        return $this->container;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }
}
