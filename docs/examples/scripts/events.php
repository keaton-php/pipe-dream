<?php

use Illuminate\Support\Facades\Event;
use Keaton\PipeDream\PipeHub;

$fired = [];

Event::listen(
    [BotChecksStarted::class, BotChecksFinished::class, BotChecksSkipped::class, RuleStarting::class, RuleFinished::class],
    function ($event) use (&$fired) {
        $fired[] = class_basename($event).(isset($event->rule) ? '('.class_basename($event->rule).')' : '');
    }
);

PipeHub::create()
    ->name('Bot checks')
    ->addPipes([DetectHostingProviderIp::class, MatchKnownBotUserAgents::class])
    ->broadcastEventBefore(BotChecksStarted::class)          // before the first rule
    ->broadcastEventAfter(BotChecksFinished::class)          // after the last rule, before then()
    ->broadcastEventOnSkip(BotChecksSkipped::class)          // instead of both, when the hub is skipped
    ->broadcastEventBeforeEachPipe(RuleStarting::class)      // just before each rule runs
    ->broadcastEventAfterEachPipe(RuleFinished::class)       // as each rule hands off to the next
    ->sendIt((object) ['id' => 'clk_134', 'ip' => '203.0.113.9', 'userAgent' => 'Mozilla/5.0']);

implode(', ', $fired);   // 'BotChecksStarted, RuleStarting(DetectHostingProviderIp), RuleFinished(DetectHostingProviderIp), RuleStarting(MatchKnownBotUserAgents), RuleFinished(MatchKnownBotUserAgents), BotChecksFinished'
