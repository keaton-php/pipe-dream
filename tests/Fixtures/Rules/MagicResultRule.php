<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class MagicResultRule extends PipeRule
{
    public function magicResultRule($subject)
    {
        $this->setBotScoreResult(0.25);

        Journal::record("bot_score=".$this->getBotScoreResult());

        return self::SUCCESS;
    }
}
