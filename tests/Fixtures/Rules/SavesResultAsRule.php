<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;

class SavesResultAsRule extends PipeRule
{
    public static $saveResultAs = 'verdict';

    public function savesResultAsRule($subject)
    {
        return 'approved';
    }
}
