<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

use R1kon\PipeDream\Exceptions\PipeRuleRedirect;

class RedirectRule extends PipeRule
{
    public function redirectRule($subject)
    {
        Journal::record("RedirectRule");

        PipeRuleRedirect::toUrl("https://example.test/landing", $subject);
    }
}
