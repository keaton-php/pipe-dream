<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class SavesResultAsRule extends PipeRule
{
    public static $saveResultAs = "verdict";

    public function savesResultAsRule($subject)
    {
        return "approved";
    }
}
