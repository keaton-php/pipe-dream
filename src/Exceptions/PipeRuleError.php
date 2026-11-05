<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;
use Throwable;

/**
 * A rule failed. The failure is logged against the rule and the hub carries
 * on, unless the hub uses stopOnError() or the rule sets $errorsAreFatal.
 */
class PipeRuleError extends Exception
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
