# Pipe Dream

Pipe Dream lets you write a Laravel business flow as named stages of small rules, and records every step it takes so you can always see why a request ended up where it did.

```bash
composer require keaton-php/pipe-dream

# only if you want to keep logs in the database (see "The log" below)
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

The service provider is discovered automatically. Here is a flow written with Pipe Dream, rendered from its own code by the demo app further down:

![A flow rendered from its code](docs/01-flow.png)

## One hub, with closures

A **hub** is an ordered list of steps (called **pipes**) that a subject passes through. The subject can be anything: here it is an incoming ad click, as a plain object.

`sendIt()` wraps the click in a `PipeEnvelope` and hands that to each pipe in turn. The envelope passes property reads, property writes and method calls straight through to your object, so `$click->country` and `$click->destination = …` work as you would expect. It also carries things of its own: values the pipes share, written with `set($key, $value)` and read with `get($key)`, every pipe's result, and a log.

<!-- example: scripts/click-check.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$click = (object) ['id' => 'clk_123', 'campaign' => 'spring-sale', 'country' => 'CA'];

$envelope = PipeHub::create()
    ->name('Check the click')
    ->addPipes([
        function ($click) {
            $click->set('region', in_array($click->country, ['CA', 'US', 'MX']) ? 'North America' : 'Elsewhere');
        },
        function ($click) {
            $click->destination = "https://example.test/{$click->campaign}";
        },
    ])
    ->sendIt($click);
```

Each pipe is timed and its result is recorded. A closure's result is whatever it returns; returning nothing counts as success (`1`). Print the log as a timeline:

<!-- example: scripts/render-timeline.php -->
```php
$t0 = $envelope->logs()[0]['time'];

foreach ($envelope->logs() as $record) {
    printf("%7.3f ms  %-11s %-28s %s\n",
        ($record['time'] - $t0) * 1000, $record['type'], class_basename($record['key']), $record['data']);
}
```

<!-- output: scripts/click-check.php -->
```text
  0.000 ms  info        PipeHub:sendIt               Sending subject through all pipes
  0.232 ms  timerStart  Closure@click-check.php:10   Executing rule Closure@click-check.php:10
  0.436 ms  returnValue Closure@click-check.php:10   1
  0.440 ms  timerEnd    Closure@click-check.php:10   Finished executing rule Closure@click-check.php:10
  0.444 ms  timerStart  Closure@click-check.php:13   Executing rule Closure@click-check.php:13
  0.453 ms  returnValue Closure@click-check.php:13   1
  0.454 ms  timerEnd    Closure@click-check.php:13   Finished executing rule Closure@click-check.php:13
  0.457 ms  info        PipeHub:sendIt               Finalized result!
```

A closure's log key is the file and line it was written on. Your times will differ.

## The same hub, with PipeRule classes

Closures are fine for small steps. For anything you want to name, test or reuse, write a rule class. A rule has one public method named after the class. Its first parameter receives the envelope, whatever you call it; any other parameter is built and injected by Laravel's container (`RegionMap` below is an ordinary class of yours that maps CA, US and MX to North America). Inside the rule, `$this->info()` and `$this->warning()` write a line to the log against the rule:

<!-- example: ResolveRegion.php -->
```php
use Keaton\PipeDream\PipeRule;

class ResolveRegion extends PipeRule
{
    public static $name = 'Resolve the region';
    public static $description = 'Which part of the world is the visitor in?';

    public function resolveRegion($click, RegionMap $regions)
    {
        $click->set('region', $regions->regionOf($click->country));
        $this->info("Visitor is in {$click->get('region')}");

        return self::SUCCESS;
    }
}
```

<!-- example: ChooseLandingPage.php -->
```php
use Keaton\PipeDream\PipeRule;

class ChooseLandingPage extends PipeRule
{
    public static $name = 'Choose the landing page';
    public static $description = 'Send the visitor to the campaign\'s page';

    public function chooseLandingPage($click)
    {
        $click->destination = "https://example.test/{$click->campaign}";

        return self::SUCCESS;
    }
}
```

The hub takes class names instead of closures (the click is the same one as above). From here on, a line ending in `// value` shows what that expression gives you:

<!-- example: scripts/rule-hub.php -->
```php
$envelope = PipeHub::create()
    ->name('Check the click')
    ->addPipes([
        ResolveRegion::class,
        ChooseLandingPage::class,
    ])
    ->sendIt($click);

$envelope->get(ResolveRegion::class);     // 1 (PipeRule::SUCCESS)
$envelope->get('region');                 // 'North America'
$click->destination;                      // 'https://example.test/spring-sale'
```

What a class gives you over a closure:

- **A name and a description.** `$name` and `$description` are labels for people and tools. The demo's flow page, for instance, is rendered from them.
- **Its own log key.** Everything the rule logs is filed under its class name.
- **Dependencies.** The run method is called through the container, so it can ask for any service. The hub finds it by name (`resolveRegion()` in `ResolveRegion`); set `public static $runFunction = 'check';` to use another name.
- **A recorded result.** The return value is stored on the envelope under the class name, so later rules can read it with `$click->get(ResolveRegion::class)`.

`addPipe()` and `addPipes()` throw a `PipeHubException` for a class that does not exist or is not a `PipeRule`, so a typo never quietly drops a step.

## What a rule can return

<!-- example: CheckCampaignStatus.php -->
```php
use Keaton\PipeDream\PipeRule;

class CheckCampaignStatus extends PipeRule
{
    public static $name = 'Check the campaign status';
    public static $description = 'Only live campaigns buy clicks';

    private const CAMPAIGNS = ['spring-sale' => 'live', 'winter-sale' => 'paused', 'demo' => 'test'];

    public function checkCampaignStatus($click)
    {
        $status = self::CAMPAIGNS[$click->campaign] ?? null;

        if ($status === null) {
            $this->fatal("Unknown campaign {$click->campaign}");
        }

        if ($status === 'paused') {
            $this->error("Campaign {$click->campaign} is paused");
        }

        if ($status === 'test') {
            return $this->skip('Test campaign, nothing to check');
        }

        return self::SUCCESS;
    }
}
```

| In the rule | Recorded | What happens next |
|---|---|---|
| `return self::SUCCESS;` (or return nothing) | `SUCCESS` (1) | the next pipe runs |
| `return self::FAIL;` | `FAIL` (2) | the next pipe runs; no error record is added |
| `return $this->skip('why');` | `SKIP` (4), and the reason | the next pipe runs |
| `$this->error('why');` | `FAIL` (2), and an error record | the next pipe runs |
| `$this->fatal('why');` | `FATAL_FAIL` (3), and a fatal record | the rest of this hub is skipped |
| any other exception or PHP error | `FAIL` (2), and an error record | the next pipe runs |

`error()` and `fatal()` stop the rule at that line, and their records point at that line in your rule. The next sections show how a hub can react to these differently.

## Stages in order: PipeManager

A real flow has several stages. A `PipeManager` runs hubs in order, all on the same envelope, so the whole run has one log and one set of results:

<!-- example: scripts/manager.php -->
```php
$click = (object) ['id' => 'clk_124', 'campaign' => 'spring-sale', 'country' => 'US'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Validate')
        ->description('Is this a click we can buy?')
        ->addPipes([CheckCampaignStatus::class]),

    PipeHub::create()
        ->name('Route')
        ->description('Where does the visitor go?')
        ->addPipes([ResolveRegion::class, ChooseLandingPage::class]),
])
    ->name('Ad click intake')
    ->subject($click)
    ->sendIt();

$envelope->flowName();                    // 'Ad click intake'
$envelope->results()->count();            // 3
$click->destination;                      // 'https://example.test/spring-sale'
```

Give hubs a `name()` and a `description()`: they are written into the log, and they make the flow read like a table of contents. The envelope has a `runId()`, a uuid that identifies this run.

## Adding behaviour

Each of these is one method on a hub.

### Skip a stage: `skipIf()`

Suppression lists do not apply to newsletter-type campaigns. `CheckAdvertiserSuppressionList` is a rule that calls `fatal()` when the visitor has opted out:

<!-- example: scripts/skip-if.php -->
```php
$click = (object) ['id' => 'clk_125', 'campaignType' => 'newsletter', 'email' => 'opted-out@example.test'];

$envelope = PipeHub::create()
    ->name('Suppression')
    ->addPipes([CheckAdvertiserSuppressionList::class])
    ->skipIf(fn ($click) => $click->campaignType === 'newsletter')
    ->sendIt($click);

$envelope->get(CheckAdvertiserSuppressionList::class);   // 4 (PipeRule::SKIP)
$envelope->hasFatalErrors();                             // false
```

When the callback returns true, no rule in the hub runs, but each one is still logged as skipped (`SKIP`, with the reason), so the log shows that the check was considered and deliberately left out. Callbacks get the envelope as their first parameter, whatever it is called.

### Decide after a stage: `then()`

`then()` runs after the hub's last rule, and can look at everything the rules recorded. Here each bot check adds one to a shared `bot_signals` value (with `$click->set()`) when it spots a sign of a bot, and returns `FAIL`:

<!-- example: scripts/then.php -->
```php
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
```

A `PipeManager` has a `then()` too, which runs after the last hub.

### End the whole flow on a fatal: `stopOnFatal()`

By default a fatal ends only the hub it happened in, and the flow moves on to the next hub. With `stopOnFatal()`, a fatal in that hub ends the whole flow:

<!-- example: scripts/stop-on-fatal.php -->
```php
$click = (object) ['id' => 'clk_127', 'campaign' => 'no-such-campaign', 'destination' => null];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Validate')
        ->addPipes([CheckCampaignStatus::class])
        ->stopOnFatal(),

    PipeHub::create()
        ->name('Route')
        ->addPipes([ChooseLandingPage::class]),
])->subject($click)->sendIt();

$envelope->hasFatalErrors();              // true
$click->destination;                      // null
```

Leave `stopOnFatal()` off and the Route hub still runs. The manager's `then()` does not run when the flow ends early, and `sendIt()` still returns the envelope, so you can check `hasFatalErrors()`.

### Handle a failure: `onFailure()` and `failureIsFatal()`

A rule normally logs an unexpected exception and carries on. A rule that calls `$this->withoutErrorHandling()` lets it reach the hub instead, which counts as a **failure**: the hub stops, and `onFailure()` runs.

<!-- example: RecordBudgetSpend.php -->
```php
use Keaton\PipeDream\PipeRule;

class RecordBudgetSpend extends PipeRule
{
    public static $name = 'Record the budget spend';
    public static $description = 'Charge the click to the campaign budget';

    public function recordBudgetSpend($click, BudgetLedger $ledger)
    {
        // If the ledger throws, let the exception reach the hub.
        $this->withoutErrorHandling();

        $ledger->charge($click->campaign, 0.40);
        $this->info("Charged {$click->campaign} 0.40");

        return self::SUCCESS;
    }
}
```

`BudgetLedger` is an ordinary class of yours; this one can be told the budget service is down:

<!-- example: BudgetLedger.php -->
```php
class BudgetLedger
{
    public function __construct(public bool $available = true)
    {
    }

    public function charge(string $campaign, float $amount): void
    {
        if (! $this->available) {
            throw new \RuntimeException('Budget service unavailable');
        }

        // A real ledger would write to the budget store here.
    }
}
```

With the ledger unavailable, `charge()` throws:

<!-- example: scripts/on-failure.php -->
```php
// The budget service is down, so every charge() throws.
app()->instance(BudgetLedger::class, new BudgetLedger(available: false));

$click = (object) ['id' => 'clk_128', 'campaign' => 'spring-sale', 'needsReview' => false, 'destination' => null];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Fulfillment')
        ->addPipes([RecordBudgetSpend::class])
        ->onFailure(function ($click, $exception) {
            $click->needsReview = true;
        })
        ->failureIsFatal(),

    PipeHub::create()
        ->name('Route')
        ->addPipes([ChooseLandingPage::class]),
])->subject($click)->sendIt();

$click->needsReview;                      // true
$envelope->hasFatalErrors();              // true
$click->destination;                      // null
```

`onFailure()` receives the exception as `$exception`. Without `failureIsFatal()`, the failure ends only this hub; with it, the whole flow ends.

### Stop a stage on an error: `stopOnError()`

By default an `error()` is logged and the hub carries on. With `stopOnError()`, an error ends the hub as a failure (so `onFailure()` would run):

<!-- example: scripts/stop-on-error.php -->
```php
$click = (object) ['id' => 'clk_129', 'campaign' => 'winter-sale', 'destination' => null];

$envelope = PipeHub::create()
    ->name('Validate')
    ->addPipes([CheckCampaignStatus::class, ChooseLandingPage::class])
    ->stopOnError()
    ->sendIt($click);

$envelope->get(CheckCampaignStatus::class);   // 2 (PipeRule::FAIL)
$click->destination;                          // null
```

### Send the visitor somewhere else

A rule can hand control back to your controller by throwing `PipeRuleRedirect` (`PipeRuleRedirect::toUrl($url)` or `::toRoute($name)`) or `PipeRuleLoadView` (`PipeRuleLoadView::load($view, $data)`). These are never treated as failures: they come straight out of the hub (and the manager), after the log is saved.

<!-- example: ChallengeGraylistedPublishers.php -->
```php
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
```

<!-- example: scripts/load-view.php -->
```php
$click = (object) ['id' => 'clk_130', 'publisher' => 'pub-77'];

try {
    PipeHub::create()
        ->name('Blacklist & graylist')
        ->addPipes([ChallengeGraylistedPublishers::class])
        ->sendIt($click);
} catch (PipeRuleLoadView $e) {
    return view($e->view, $e->data);      // the 'challenge' view, with ['click' => 'clk_130']
}
```

## The log

### Reading it

<!-- example: scripts/log-reading.php -->
```php
$click = (object) ['id' => 'clk_131', 'campaign' => 'no-such-campaign'];

$envelope = PipeHub::create()->addPipes([CheckCampaignStatus::class])->sendIt($click);

$envelope->hasErrors();                                  // false
$envelope->hasFatalErrors();                             // true
$envelope->results()->fatalFailures()->count();          // 1
$envelope->logger()->fatals()->first()['data'];          // 'Unknown campaign no-such-campaign [.../CheckCampaignStatus.php | line ...]'
basename($envelope->logger()->fatals()->first()['context']['file']);   // 'CheckCampaignStatus.php'
```

Every record is an array with a `time`, a `key` (the rule class, a closure's file and line, or `PipeHub:sendIt` / `PipeManager:sendIt`), a `type` (`info`, `warning`, `error`, `fatal`, `timerStart`, `timerEnd`, `returnValue`), its `data` and a `context` array. The logger has slices for the common questions: `errors()`, `fatals()`, `warnings()`, `timers()`, `returnValues()` and `recordsByKey($key)`. Add your own lines with `$envelope->logger()->info($key, $message)`.

### How much to record

To set anything on the envelope before a run, wrap the subject yourself with `PipeEnvelope::wrap()` and send the envelope:

<!-- example: scripts/log-level.php -->
```php
$click = (object) ['id' => 'clk_132', 'campaign' => 'no-such-campaign'];

$envelope = PipeEnvelope::wrap($click)->logLevel(PipeLogger::LEVEL_PROBLEMS);

PipeHub::create()->addPipes([CheckCampaignStatus::class])->sendIt($envelope);

count($envelope->logs());                 // 2 (the rule's fatal, and the hub's stop warning)
$envelope->results()->count();            // 1
```

`PipeLogger::LEVEL_EVERYTHING` is the default; `LEVEL_NO_TIMERS` drops the rule timers; `LEVEL_PROBLEMS` keeps only warnings, errors and fatals. Results are recorded whatever the level.

### Keeping it: one row per run

With the migration published and run, `DatabasePersistStrategy` stores each run as one row in `pipe_dream_logs`:

<!-- example: scripts/database.php -->
```php
$click = (object) ['id' => 'clk_133', 'campaign' => 'spring-sale', 'country' => 'CA'];

$envelope = PipeEnvelope::wrap($click)->persistWith(new DatabasePersistStrategy());

PipeManager::create([
    PipeHub::create()->name('Validate')->addPipes([CheckCampaignStatus::class]),
    PipeHub::create()->name('Route')->addPipes([ResolveRegion::class, ChooseLandingPage::class]),
])->name('Ad click intake')->subject($envelope)->sendIt();

$envelope->persist();                     // saving again updates the same row

PipeDreamLog::where('run_id', $envelope->runId())->count();   // 1
```

The row holds the run id, the flow's name, the log (JSON), `has_errors` / `has_fatals`, and the subject's type and id when the subject is a saved Eloquent model. It is written with a single upsert keyed on the run id, so saving the same run again updates its row instead of adding a second one. To use it for every flow, bind it once:

<!-- example: scripts/bind-strategy.php -->
```php
// In a service provider's register() method:
$this->app->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);
```

A `PipeManager` saves the log once, when the flow ends, whether it completed, stopped early or threw. A hub run on its own saves when it finishes. Turn this off with `->persistLogs(false)` and call `$envelope->persist()` yourself when you want to save at a moment of your choosing.

### Reading a run back later

<!-- example: scripts/rebuild.php -->
```php
$run = PipeDreamLog::forRun($envelope->runId())->firstOrFail();

$run->flow;                               // 'Ad click intake'
$run->has_fatals;                         // false
$run->logger()->records() == $envelope->logs();   // true
```

`PipeDreamLog::logger()` rebuilds the stored records into a logger, ready for a timeline view. `PipeDreamLog::withProblems()` finds runs with errors or fatals.

## Putting it together: managing a click

This is how the pieces combine in a click-management flow: an incoming ad click is validated and given a session, tracked, checked against geo exclusions, checked against the advertiser's suppression list (skipped for newsletter-type campaigns), checked against blacklists and graylists, checked for bots (skipped when a campaign turns bot checks off), counted against caps and budget, and routed.

<!-- example: HandleAdClick.php -->
```php
use Illuminate\Http\Request;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

class HandleAdClick
{
    public function __invoke(Request $request)
    {
        $click = PipeEnvelope::wrap(new IncomingClick($request))
            ->persistWith(new DatabasePersistStrategy());

        $started = microtime(true);

        try {
            PipeManager::create([

                PipeHub::create()
                    ->name('Validate & start session')
                    ->description('Is this a complete, signed click for a live campaign?')
                    ->addPipes([
                        RequireTrackingParameters::class,
                        VerifyClickSignature::class,
                        RecognizeCampaign::class,
                        StartClickSession::class,
                    ])
                    ->stopOnFatal(),             // a bad click ends the whole flow here

                PipeHub::create()
                    ->name('Tracking')
                    ->description('Attribution cookie and conversion-path tracking')
                    ->addPipes([
                        SetAttributionCookie::class,
                        RecordConversionPath::class,
                    ]),

                PipeHub::create()
                    ->name('Geo exclusions')
                    ->description('Is the visitor somewhere this campaign buys traffic?')
                    ->addPipes([
                        ResolveVisitorCountry::class,
                        EnforceCampaignCountries::class,
                        EnforceAdvertiserGeoExclusions::class,
                    ])
                    ->stopOnFatal(),

                PipeHub::create()
                    ->name('Suppression')
                    ->description('Has this visitor asked the advertiser not to contact them?')
                    ->addPipes([
                        CheckAdvertiserSuppressionList::class,
                    ])
                    ->skipIf(fn ($click) => $click->campaignSettings()->isNewsletter())
                    ->stopOnFatal(),

                PipeHub::create()
                    ->name('Blacklist & graylist')
                    ->description('Checks that are never skipped')
                    ->addPipes([
                        MatchIpBlacklist::class,
                        MatchPublisherBlacklist::class,
                        ChallengeGraylistedPublishers::class,
                    ])
                    ->then(fn ($click) => $click->isListed() ? $click->flag('Blacklisted') : null),

                PipeHub::create()
                    ->name('Bot checks')
                    ->description('Does this look like a person, not a script or a server?')
                    ->addPipes([
                        DetectHostingProviderIp::class,
                        MatchKnownBotUserAgents::class,
                        ScoreClickVelocity::class,
                    ])
                    ->skipIf(fn ($click) => $click->campaignSettings()->bot_checks_disabled)
                    ->then(fn ($click) => $click->get('bot_signals') >= 2 ? $click->flag('Two or more bot signals') : null),

                PipeHub::create()
                    ->name('Fulfillment & caps')
                    ->description('Daily caps and budget, only for clicks we will pay for')
                    ->addPipes([
                        EnforceDailyClickCap::class,
                        RecordBudgetSpend::class,
                    ])
                    ->skipIf(fn ($click) => $click->isFlagged() || $click->isTest()),

                PipeHub::create()
                    ->name('Route')
                    ->description('Landing page, review queue or a safe dead end')
                    ->addPipes([
                        ChooseDestination::class,
                    ]),

            ])
                ->name('Ad click intake')
                ->subject($click)
                ->persistLogs(false)             // we persist once, below, after the timer
                ->then(fn ($click) => $click->markComplete())
                ->sendIt();

        } catch (PipeRuleRedirect $e) {
            return redirect()->to($e->redirectTo);
        } catch (PipeRuleLoadView $e) {
            return view($e->view, $e->data);
        } finally {
            $click->logger()->info('Intake', sprintf('Handled in %.1f ms', (microtime(true) - $started) * 1000));
            $click->persist();                   // one row per click, whatever happened
        }

        return redirect()->to($click->destination());
    }
}
```

The rules are ordinary `PipeRule` classes like the ones above. `IncomingClick` is your own class: it has the same fields as the plain clicks in the earlier examples (`id`, `campaign`, `country`, `ip`, `userAgent`, `publisher`, `email`), so the same rules work on it, plus a few methods the flow calls through the envelope: `campaignSettings()` (the campaign's countries, type and bot-check switch), `flag()`, `isFlagged()`, `isListed()`, `isTest()`, `markComplete()` and `destination()`. The controller persists once in `finally`, after recording how long the click took, so every click gets exactly one row, including the ones that redirect or end early.

## See it running

A demo app pushes 500 synthetic ad clicks through a five-stage, 14-rule flow. Every page is built from the flow definition and the persisted logs, with nothing written by hand.

| Dashboard built from the logs | One click's full story | The whole flow is one readable file |
|---|---|---|
| ![Dashboard](docs/02-dashboard.png) | ![One click's story](docs/03-click-story.png) | ![Source](docs/04-code.png) |

## Reference

### Stopping, in one table

| On the hub | A rule calls `fatal()` | A rule calls `error()` | An exception escapes a rule |
|---|---|---|---|
| (default) | the hub ends: its remaining rules and `then()` are skipped, the next hub runs | logged as `FAIL`, the hub carries on | the hub ends as a failure, `onFailure()` runs, the next hub runs |
| `->stopOnFatal()` | **the whole flow ends**: no later hub, and the manager's `then()` does not run | as default | as default |
| `->stopOnError()` | as default | the hub ends as a failure, `onFailure()` runs, the next hub runs | as default |
| `->failureIsFatal()` | as default | as default (or the flow ends, with `stopOnError()`) | `onFailure()` runs, a fatal is logged and **the whole flow ends** |

"Exception" means any `Throwable`, PHP errors such as `TypeError` included. For unexpected exceptions, only `withoutErrorHandling()` lets one escape a rule; otherwise it is logged as a `FAIL` and the hub carries on. Fatals, errors under `stopOnError()`, redirects, views and configuration errors leave a rule by design. A configuration error (`PipeHubException`) is never treated as a failure: it is thrown straight out of the flow.

### Skipping, in one table

| | What runs | What is logged | `then()` |
|---|---|---|---|
| `->skipIf(fn ($s) => …)` returns true | no rule executes | every rule, as `SKIP` with the reason | runs |
| `->skip()` | nothing in the hub | one "Skipping hub" line | does not run |
| `$envelope->skipAll()` (from a rule or callback) | nothing after that point, in this hub or any later hub | every remaining rule, as `SKIP` | runs |
| a rule returns `$this->skip('…')` | the rule ran and decided to skip | that rule, as `SKIP` with the reason | runs |

### Rule options

- `public static $saveResultAs = 'ip_verdict';` also stores the rule's result under that key.
- `protected $errorsAreFatal = true;` turns the rule's `error()` calls into fatals.
- `$this->setBotScoreResult(0.4)` / `$this->getBotScoreResult()` write and read `bot_score` on the envelope while the rule runs.
- A closure pipe can sit anywhere in `addPipes([...])`, next to rule classes.

### Events

A hub can dispatch Laravel events at five points. Hub events are constructed with the envelope; per-rule events with the envelope and the rule's key. Declare the event classes:

<!-- example: scripts/event-classes.php -->
```php
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

Then name them on the hub:

<!-- example: scripts/events.php -->
```php
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

`before` and `after` bracket the rules, `beforeEach`/`afterEach` surround each rule in turn, and a skipped hub dispatches only the skip event. A rule that stops the hub never hands off, so its `afterEach` does not fire; skipped rules fire neither. Every dispatch is written to the log under `PipeHub:event`.

### Rule copies: shared and transient bindings

Each run resolves the rule from the container. An ordinary (transient) binding runs exactly as the container returned it. A rule bound as shared (`singleton()`, `instance()`, `scoped()`, or the container's `#[Singleton]`/`#[Scoped]` attributes on Laravel versions that have them) runs as a clone, so the shared object is never executed or changed and runs never see each other's state. A shared rule that cannot be cloned (a private `__clone()`, or one that throws) is a configuration error: the flow throws a `PipeHubException` telling you to bind it non-shared, rather than silently skipping the rule. Keep anything that must outlive a run in an injected service. A hub given its own container (`PipeHub::create($container)`) uses it for everything in the run.

### JSON-safe log values

Every value is made JSON-safe as it is recorded, so saving a log can never fail and hide what the flow did. A rule may return anything, but `NAN` and `INF` are logged as the strings `"NAN"` / `"INF"`, resources as `"resource(stream)"`, closures as `"Closure"`, enums as their value, dates as ISO 8601 strings, exceptions as their class and message, other objects as their public properties plus a `__class` key (or what their `jsonSerialize()`, `toArray()` or `__toString()` gives), cycles as `"[recursion: Class]"`, nesting past 16 levels as `"[max depth]"`, and invalid UTF-8 bytes are replaced. The raw value is still what `$envelope->get(Rule::class)` and `$envelope->results()` return.

### Other storage formats and places

`new DatabasePersistStrategy(new PipeLoggerSerializeStrategy())` stores PHP `serialize()` output instead of JSON (rebuilt with `allowed_classes => false`, so a stored log can never instantiate objects); pass the same strategy to `PipeDreamLog::logger()` to read it back. A run is saved with the strategy given to `$envelope->persistWith()`, else the one bound to `PipeLoggerPersistStrategy` in the container; with neither, nothing is written. To store logs somewhere else, implement `PipeLoggerPersistStrategy`, which has one method, `persist(PipeEnvelope $subject)`; `$subject->logger()->output()` gives you the log as JSON.

### Introspecting a flow

Tools can read a flow back without reflection, which is how the demo renders its flow page from the same objects that process the clicks: `PipeManager::getName()`, `getDescription()`, `getHubs()`; `PipeHub::getName()`, `getDescription()`, `getPipes()`, `getSkipIf()`, `getThen()`, `getOnFailure()`, `getEvents()`, `stopsOnFatal()`, `stopsOnError()`, `isFailureFatal()`, `isSkipped()`.

### Compatibility

Tested on Laravel 9–13 (CI matrix) with PHP 8.1–8.4, each Laravel major on the PHP versions it supports: Laravel 9 on 8.1–8.2, 10 on 8.1–8.3, 11 and 12 on 8.2–8.4, 13 on 8.3–8.4.

### Testing

```bash
composer install
composer test
```

The suite uses Orchestra Testbench, and its database tests run on in-memory SQLite. Every PHP example in this README is run, as written, by `tests/Feature/ReadmeExamplesTest.php`.

### Changelog and license

See [CHANGELOG.md](CHANGELOG.md) for what changed. MIT licensed; see [LICENSE](LICENSE).
