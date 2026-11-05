<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;

/**
 * Thrown by a rule that wants a view rendered instead of continuing. It is
 * never swallowed: it propagates out of the hub and the manager so the
 * caller can render $view with $data.
 */
class PipeRuleLoadView extends Exception
{
    public $view;

    public $data;

    public function __construct($view, $data = [])
    {
        $this->view = $view;
        $this->data = $data;

        parent::__construct("Loading view [{$view}]");
    }

    public static function load($view, $parameters = [])
    {
        throw new static($view, $parameters);
    }
}
