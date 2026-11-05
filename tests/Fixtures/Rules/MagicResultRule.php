<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class MagicResultRule extends PipeRule
{
    public function magicResultRule($subject)
    {
        $this->setBotScoreResult(0.25);

        Journal::record("bot_score=".$this->getBotScoreResult());

        return self::SUCCESS;
    }
}
