<?php
namespace R1kon\PipeDream;

use Illuminate\Support\Collection;

class PipeRuleResults extends Collection
{
    public function successes()
    {
        return $this->filter(function($result) {
            return $result->result() === PipeRule::SUCCESS;
        });
    }

    public function failures()
    {
        return $this->filter(function($result) {
            return $result->result() === PipeRule::FAIL;
        });
    }

    public function fatalFailures()
    {
        return $this->filter(function($result) {
            return $result->result() === PipeRule::FATAL_FAIL;
        });
    }

    public function skips()
    {
        return $this->filter(function($result) {
            return $result->result() === PipeRule::SKIP;
        });
    }

    public function hasFailures()
    {
        return $this->failures()->isNotEmpty();
    }

    public function hasFatalFailures()
    {
        return $this->fatalFailures()->isNotEmpty();
    }

}