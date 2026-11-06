<?php

use Keaton\PipeDream\PipeHub;

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
