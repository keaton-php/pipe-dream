<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;
use Keaton\PipeDream\Concerns\RecordsOrigin;
use Throwable;

/**
 * A rule failed. The failure is logged against the rule, the hub's
 * onError() runs and the next pipe runs, unless errors are fatal (the
 * hub's errorsAreFatal() or the rule's $errorsAreFatal).
 */
class PipeRuleError extends Exception
{
    use RecordsOrigin;

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
