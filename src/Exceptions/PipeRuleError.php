<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;
use Keaton\PipeDream\Concerns\RecordsOrigin;
use Throwable;

/**
 * A rule failed. The failure is logged against the rule and the hub carries
 * on, unless the hub uses stopOnError() or the rule sets $errorsAreFatal.
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
