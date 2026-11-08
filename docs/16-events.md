<!-- nav-group: Advanced -->
# Events

How do I listen to what a flow does while it runs?

A hub can dispatch Laravel events at five points: before its first rule, after its last, when it is skipped, and around each rule. Hub events are constructed with the envelope; per-rule events with the envelope and the rule's key.

## Declare the events

<!-- example: docs/examples/BotCheckEvents.php -->
```php
<?php

use Keaton\PipeDream\PipeEnvelope;

class BotChecksStarted
{
    public function __construct(public PipeEnvelope $click) {}
}

class BotChecksFinished
{
    public function __construct(public PipeEnvelope $click) {}
}

class BotChecksSkipped
{
    public function __construct(public PipeEnvelope $click) {}
}

class RuleStarting
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}

class RuleFinished
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}
```

## Name them on the hub

<!-- example: docs/examples/scripts/events.php -->
```php
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
```

## When each one fires

`before` and `after` bracket the rules, `beforeEach`/`afterEach` surround each rule in turn, and a skipped hub dispatches only the skip event.

- A rule that stops the hub never hands off, so its `afterEach` does not fire.
- Rules skipped without running (by `skipIf()`, `skipAll()` or the rule's own skip flag) fire neither `beforeEach` nor `afterEach`, while a rule that runs and returns `$this->skip()` fires both like any other.
- Every dispatch is written to the log under `PipeHub:event`.

Next: [Introspecting a flow](17-introspection.md).
