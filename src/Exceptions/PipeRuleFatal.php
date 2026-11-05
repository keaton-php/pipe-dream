<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;
use Throwable;

/**
 * A rule hit something it cannot continue past. By default a fatal ends the
 * hub it happened in; with stopOnFatal() on that hub it ends the whole flow.
 */
class PipeRuleFatal extends Exception
{
    protected $subject;

    public function __construct($message = '', $subject = null, $code = 0, ?Throwable $previous = null)
    {
        $this->subject = $subject;

        parent::__construct($message, $code, $previous);
    }

    public function subject()
    {
        return $this->subject;
    }
}
