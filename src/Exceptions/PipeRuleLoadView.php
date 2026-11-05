<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;

class PipeRuleLoadView extends Exception
{
    public $view;
    public $data;

    public function __construct($view, $data)
    {
        $this->view = $view;
        $this->data = $data;
    }

    public static function load($view, $parameters)
    {
        throw new static($view, $parameters);
    }

}
