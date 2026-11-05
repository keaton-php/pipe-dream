<?php

namespace R1kon\PipeDream;

class PipeRuleResult
{
    protected $rule;
    protected $result;
    
    public function __construct($rule, $result)
    {
        $this->rule = $rule;
        $this->result = $result;
    }

    public function rule()
    {
        return $this->rule;
    }

    public function result()
    {
        return $this->result;
    }

    public function isFail()
    {
        return $this->result === PipeRule::FAIL;
    }

    public function isSuccess()
    {
        return $this->result === PipeRule::SUCCESS;
    }

    public function isFatal()
    {
        return $this->result === PipeRule::FATAL_FAIL;
    }
}