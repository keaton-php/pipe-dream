<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;

class PipeRuleFatal extends Exception
{

    protected $subject;

    public function __construct($message, $subject, $code = 0, ?Exception $previous = null)
    {
        $this->subject = $subject;
        parent::__construct($message, $code, $previous);
    }

    public function subject()
    {
        return $this->subject;
    }
}
