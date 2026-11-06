<?php

use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\PipeRule;

class ChallengeGraylistedPublishers extends PipeRule
{
    public static $name = 'Challenge graylisted publishers';
    public static $description = 'Traffic from a doubtful publisher gets a challenge page';

    public function challengeGraylistedPublishers($click)
    {
        if (in_array($click->publisher, ['pub-77'], true)) {
            PipeRuleLoadView::load('challenge', ['click' => $click->id]);
        }

        return self::SUCCESS;
    }
}
