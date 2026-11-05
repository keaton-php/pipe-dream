<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;

class PipeRuleRedirect extends Exception
{
    public $redirectTo;
    public $subject; //Yeah it's public, so what?

    public function __construct($redirectTo, $subject)
    {
        $this->redirectTo = $redirectTo;
        $this->subject = $subject;
    }

    public static function toUrl($url, $subject = null)
    {
        throw new static($url, $subject);
    }

    public static function toRoute($route, $subject = null)
    {
        throw new static(route($route), $subject);
    }

}
