<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class CheckAdvertiserSuppressionList extends PipeRule
{
    public static $name = 'Check the advertiser suppression list';
    public static $description = 'People who opted out never reach the advertiser';

    public function checkAdvertiserSuppressionList($click, SuppressionList $list)
    {
        if ($list->contains($click->email)) {
            $this->fatal('Visitor is on the advertiser suppression list');
        }

        return self::SUCCESS;
    }
}
