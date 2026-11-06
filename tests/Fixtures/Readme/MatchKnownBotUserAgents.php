<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class MatchKnownBotUserAgents extends PipeRule
{
    public static $name = 'Match known bot user agents';
    public static $description = 'A user agent that says it is a bot is a bot';

    public function matchKnownBotUserAgents($click)
    {
        if (str_contains(strtolower((string) $click->userAgent), 'bot')) {
            $click->set('bot_signals', ($click->get('bot_signals') ?? 0) + 1);
            $this->warning('User agent looks like a bot');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
