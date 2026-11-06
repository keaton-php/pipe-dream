<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Readme\DetectHostingProviderIp;
use Keaton\PipeDream\Tests\Fixtures\Readme\MatchKnownBotUserAgents;

// README:start
$click = (object) ['id' => 'clk_126', 'ip' => '198.51.100.20', 'userAgent' => 'ExampleBot/2.1', 'flagged' => false];

$envelope = PipeHub::create()
    ->name('Bot checks')
    ->addPipes([
        DetectHostingProviderIp::class,
        MatchKnownBotUserAgents::class,
    ])
    ->then(function ($click) {
        if ($click->get('bot_signals') >= 2) {
            $click->flagged = true;
        }
    })
    ->sendIt($click);

$envelope->get('bot_signals');            // 2
$click->flagged;                          // true
// README:end
