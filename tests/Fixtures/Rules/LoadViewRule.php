<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

use R1kon\PipeDream\Exceptions\PipeRuleLoadView;

class LoadViewRule extends PipeRule
{
    public function loadViewRule($subject)
    {
        Journal::record("LoadViewRule");

        PipeRuleLoadView::load("clicks.unavailable", ["reason" => "paused"]);
    }
}
