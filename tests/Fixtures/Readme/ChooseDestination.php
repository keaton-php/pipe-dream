<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class ChooseDestination extends PipeRule
{
    public function chooseDestination($click)
    {
        $click->destination = $click->isFlagged()
            ? 'https://example.test/review'
            : 'https://example.test/landing/'.$click->param('campaign');

        return self::SUCCESS;
    }
}
