<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeRule;

class RouteRedirectRule extends PipeRule
{
    public function routeRedirectRule($subject)
    {
        PipeRuleRedirect::toRoute('landing', $subject, ['campaign' => 'spring']);
    }
}
