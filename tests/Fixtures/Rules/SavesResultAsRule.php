<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class SavesResultAsRule extends PipeRule
{
    public static $saveResultAs = "verdict";

    public function savesResultAsRule($subject)
    {
        return "approved";
    }
}
