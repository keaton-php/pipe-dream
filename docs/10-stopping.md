<!-- nav-group: Controlling the flow -->
# Errors, fatals and stopping

What happens when a step fails, and how do I decide how far it reaches?

When a step goes wrong, two things decide what happens next: what the rule says happened, and how far its hub lets that reach. Ask what you want to happen, and the answer is one of three:

| You want to | In the rule | On the hub |
|---|---|---|
| note it and keep going: the next pipe runs | `$this->error('why')` | nothing to set |
| end this hub and move on to the next one | `$this->fatal('why')` | `->fatalsStopThisHubOnly()` |
| stop everything | `$this->fatal('why')` | nothing to set: this is the default |

## What each call does

- `error()` records a failure (`PipeRule::FAIL`) with its message, and the next pipe runs. An unexpected exception in a rule (any `Throwable`, PHP errors such as `TypeError` included) is handled the same way.
- `fatal()` records a fatal (`PipeRule::FATAL_FAIL`), and by default nothing after it runs: not the rest of its hub, not any later hub, and neither the hub's `then()` nor the manager's `then()`.
- `fatalsStopThisHubOnly()` contains a hub's fatals: the rest of that hub and its `then()` do not run, the manager moves on to the next hub, and the manager's `then()` still runs. It only matters inside a manager; a hub run on its own is the whole flow.
- `errorsAreFatal()` on a hub makes every `error()` and every unexpected exception in its pipes a fatal, and then `fatalsStopThisHubOnly()` decides how far it reaches. A rule can do the same for itself with `protected $errorsAreFatal = true;`.

## See all five outcomes

`RejectEmptyTitle` stops on an empty title, and `CheckTitleLength` (from [Writing rules](06-rules.md)) calls `error()` on a title over twelve words:

<!-- example: docs/examples/RejectEmptyTitle.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class RejectEmptyTitle extends PipeRule
{
    public static $name = 'Reject empty titles';
    public static $description = 'A post needs a title';

    public function rejectEmptyTitle($post)
    {
        if (trim($post->title) === '') {
            $this->fatal('The post has no title');
        }

        return self::SUCCESS;
    }
}
```

Here they are in a three-hub flow, run five ways. Each line lists the rules that ran, in order, with what they recorded; a rule the stop reached never runs, so it has no result at all:

<!-- example: docs/examples/scripts/reach.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;

$publish = function (string $title, ?Closure $check = null, ?Closure $edit = null) {
    $check ??= fn (PipeHub $hub) => $hub;
    $edit ??= fn (PipeHub $hub) => $hub;
    $managerThen = 'did not run';

    $envelope = PipeManager::create([
        $check(PipeHub::create()->name('Check')->addPipe(RejectEmptyTitle::class)),
        $edit(PipeHub::create()->name('Edit')->addPipes([TidyTitle::class, CountWords::class, CheckTitleLength::class])),
        PipeHub::create()->name('Publish')->addPipe(SlugifyTitle::class),
    ])
        ->subject((object) ['title' => $title])
        ->then(function () use (&$managerThen) {
            $managerThen = 'ran';
        })
        ->sendIt();

    $outcome = [PipeRule::SUCCESS => 'ok', PipeRule::FAIL => 'error', PipeRule::FATAL_FAIL => 'FATAL', PipeRule::SKIP => 'skip'];
    $ran = $envelope->results()->map(fn ($result) => $result->rule().' '.$outcome[$result->result()])->implode(', ');

    return "{$ran}. Manager then(): {$managerThen}";
};

$long = 'A title that keeps on going for many more words than any feed will show';

echo "1. error() in Edit:\n   ", $publish($long), PHP_EOL;
echo "2. fatal() in Check:\n   ", $publish(''), PHP_EOL;
echo "3. fatal() in Check, with fatalsStopThisHubOnly():\n   ", $publish('', fn ($hub) => $hub->fatalsStopThisHubOnly()), PHP_EOL;
echo "4. error() in Edit, with errorsAreFatal():\n   ", $publish($long, null, fn ($hub) => $hub->errorsAreFatal()), PHP_EOL;
echo "5. error() in Edit, with errorsAreFatal() and fatalsStopThisHubOnly():\n   ", $publish($long, null, fn ($hub) => $hub->errorsAreFatal()->fatalsStopThisHubOnly()), PHP_EOL;
```

<!-- output: docs/examples/scripts/reach.php -->
```text
1. error() in Edit:
   RejectEmptyTitle ok, TidyTitle ok, CountWords ok, CheckTitleLength error, SlugifyTitle ok. Manager then(): ran
2. fatal() in Check:
   RejectEmptyTitle FATAL. Manager then(): did not run
3. fatal() in Check, with fatalsStopThisHubOnly():
   RejectEmptyTitle FATAL, TidyTitle ok, CountWords ok, CheckTitleLength skip, SlugifyTitle ok. Manager then(): ran
4. error() in Edit, with errorsAreFatal():
   RejectEmptyTitle ok, TidyTitle ok, CountWords ok, CheckTitleLength FATAL. Manager then(): did not run
5. error() in Edit, with errorsAreFatal() and fatalsStopThisHubOnly():
   RejectEmptyTitle ok, TidyTitle ok, CountWords ok, CheckTitleLength FATAL, SlugifyTitle ok. Manager then(): ran
```

The same decision as a lookup is on the [stop tables page](20-reference-tables.md).

Next: [Reacting to failures](11-error-hooks.md).
