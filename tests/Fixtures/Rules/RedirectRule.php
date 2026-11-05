<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;

class RedirectRule extends PipeRule
{
    public function redirectRule($subject)
    {
        Journal::record("RedirectRule");

        PipeRuleRedirect::toUrl("https://example.test/landing", $subject);
    }
}
