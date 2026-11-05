<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

use Keaton\PipeDream\Exceptions\PipeRuleLoadView;

class LoadViewRule extends PipeRule
{
    public function loadViewRule($subject)
    {
        Journal::record("LoadViewRule");

        PipeRuleLoadView::load("clicks.unavailable", ["reason" => "paused"]);
    }
}
