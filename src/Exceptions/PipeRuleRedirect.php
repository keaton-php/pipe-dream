<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;
use Illuminate\Container\Container;

/**
 * Thrown by a rule that wants the request redirected instead of continuing.
 * It is never swallowed: it propagates out of the hub and the manager so the
 * caller can redirect to $redirectTo.
 */
class PipeRuleRedirect extends Exception
{
    public $redirectTo;

    public $subject;

    public function __construct($redirectTo, $subject = null)
    {
        $this->redirectTo = $redirectTo;
        $this->subject = $subject;

        parent::__construct("Redirecting to [{$redirectTo}]");
    }

    public static function toUrl($url, $subject = null)
    {
        throw new static($url, $subject);
    }

    public static function toRoute($route, $subject = null, array $parameters = [])
    {
        throw new static(Container::getInstance()->make('url')->route($route, $parameters), $subject);
    }
}
