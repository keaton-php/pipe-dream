<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;
use Illuminate\Contracts\Container\Container;

/**
 * Thrown by a rule that wants the request redirected instead of continuing.
 * It is never swallowed: it propagates out of the hub and the manager so the
 * caller can redirect to $redirectTo.
 *
 * toRoute() does not build the URL itself: the rule, hub or manager the
 * redirect passes through resolves it with the run's container, so a flow
 * given its own container never asks the global application for a URL.
 */
class PipeRuleRedirect extends Exception
{
    public $redirectTo;

    public $subject;

    public ?string $route = null;

    public array $parameters = [];

    public function __construct($redirectTo, $subject = null, ?string $route = null, array $parameters = [])
    {
        $this->redirectTo = $redirectTo;
        $this->subject = $subject;
        $this->route = $route;
        $this->parameters = $parameters;

        parent::__construct($redirectTo !== null ? "Redirecting to [{$redirectTo}]" : "Redirecting to route [{$route}]");
    }

    public static function toUrl($url, $subject = null)
    {
        throw new static($url, $subject);
    }

    public static function toRoute($route, $subject = null, array $parameters = [])
    {
        throw new static(null, $subject, $route, $parameters);
    }

    public function isResolved(): bool
    {
        return $this->redirectTo !== null;
    }

    /**
     * Build the URL of a route redirect with the given container's URL
     * generator. Does nothing once the URL is known.
     */
    public function resolveRoute(Container $container)
    {
        if ($this->redirectTo === null && $this->route !== null) {
            $this->redirectTo = $container->make('url')->route($this->route, $this->parameters);
            $this->message = "Redirecting to [{$this->redirectTo}]";
        }

        return $this;
    }
}
