<!-- nav-group: Basic usage -->
# Skipping steps

How do I skip a hub or a rule, and see it in the log?

A skipped step is never invisible: the log records it, with the reason.

<!-- example: docs/examples/scripts/skip.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => 'work in progress', 'status' => 'draft'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->addPipes([TidyTitle::class]),

    PipeHub::create()
        ->name('Publish')
        ->skipIf(fn ($post) => $post->status === 'draft')
        ->addPipes([SlugifyTitle::class]),
])
    ->subject($post)
    ->sendIt();

foreach ($envelope->results() as $result) {
    echo $result->rule(), ': ', $result->result(), PHP_EOL;
}

echo isset($post->slug) ? 'published' : 'still a draft';
```

<!-- output: docs/examples/scripts/skip.php -->
```text
TidyTitle: 1
SlugifyTitle: 4
still a draft
```

The draft skipped the Publish hub, so `SlugifyTitle` has result `4` (`PipeRule::SKIP`) and the post has no slug.

## Skip a hub

- `->skipIf(fn ($post) => …)` returns true, or a non-empty string: the hub still runs, but every rule in it is logged as `SKIP` and none executes. The hub's `then()` still runs. A string returned is the reason each rule records (`fn ($order) => $order->isOnHold() ? 'Held for review' : null`); for a plain `true`, pass the reason as the second argument (`->skipIf(fn ($order) => $order->customerIsTrusted(), 'Trusted customer')`). `false`, `null` and `''` do not skip.
- `->skip()` skips the whole hub: nothing in it runs, not even `then()`. One "Skipping hub" line is logged.

## Skip a rule

A rule can decide it has nothing to do: `return $this->skip('A one-word title is fine');` records `SKIP` with the reason (see [Writing rules](06-rules.md)).

## Skip everything that is left

`$envelope->skipAll()`, called from a rule or a callback, skips every remaining pipe in this hub and in every later hub. Each is logged as `SKIP`.

## In one table

| | What runs | What is logged | `then()` |
|---|---|---|---|
| `->skipIf(fn ($s) => …)` returns true or a string | no rule executes | every rule, as `SKIP` with the reason (plus a `PipeHub:event` line when a skip event is set) | runs |
| `->skip()` | nothing in the hub | one "Skipping hub" line (plus a `PipeHub:event` line when a skip event is set) | does not run |
| `$envelope->skipAll()` (from a rule or callback) | nothing after that point, in this hub or any later hub | every remaining rule, as `SKIP` | runs |
| a rule returns `$this->skip('…')` | the rule ran and decided to skip | that rule, as `SKIP` with the reason | runs |

Next: [Errors, fatals and stopping](10-stopping.md).
