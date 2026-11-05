# Pipe Dream

**Pipe-driven development for Laravel.** You write a business flow as named stages of small rules, and it reads like a table of contents. Every rule that runs is logged, timed and recorded with its result, so when someone asks *"why did this request end up here?"*, you open the log instead of the debugger.

It is built on the same pipeline pattern as Laravel's own `Pipeline`, and it grew out of a high-volume production click-intake system where every incoming click passes through about ten stages and dozens of rules, with a full audit trail per click.

```php
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
                    ->skipIf(fn ($click) => $click->campaign()->isNewsletter())
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
                    ->skipIf(fn ($click) => $click->campaign()->bot_checks_disabled)
                    ->then(fn ($click) => $click->botSignals() >= 2 ? $click->flag('Two or more bot signals') : null),

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

`$click` is a `PipeEnvelope`: it wraps your own object (property reads, writes and method calls pass straight through to it) and carries the log, every rule's result, shared state (`set()` / `get()`) and a run id.

## See it running

A demo app pushes 500 synthetic ad clicks through a five-stage, 14-rule flow. Every page below is built from the flow definition and the persisted pipe logs, with nothing written by hand.

| The flow, rendered from the code | One click's full story |
|---|---|
| ![The flow](docs/01-flow.png) | ![One click's story](docs/03-click-story.png) |
| **Dashboard built from the logs** | **The whole flow is one readable file** |
| ![Dashboard](docs/02-dashboard.png) | ![Source](docs/04-code.png) |

## A rule is one small class

```php
use Keaton\PipeDream\PipeRule;

class EnforceCampaignCountries extends PipeRule
{
    public static $name = 'Enforce campaign countries';
    public static $description = 'Only countries the campaign buys get through';

    // The method named after the class runs. It is called through the
    // container, so dependencies are injected; the first parameter gets the
    // envelope whatever it is called.
    public function enforceCampaignCountries($click, GeoDatabase $geo)
    {
        $targets = $click->campaign()->countries;

        if ($targets === []) {
            return $this->skip('Campaign targets every country');
        }

        if (! in_array($click->country, $targets, true)) {
            $this->fatal("Visitor is in {$click->country}; campaign targets ".implode(', ', $targets));
        }

        return self::SUCCESS;
    }
}
```

A rule returns `SUCCESS`, `FAIL`, `FATAL_FAIL` or `SKIP` (any other value is recorded as it is), or calls:

| Call | Result recorded | What happens next |
|---|---|---|
| `return self::SUCCESS` (or nothing) | `SUCCESS` | the next rule runs |
| `return $this->skip('reason')` | `SKIP`, reason logged | the next rule runs |
| `$this->info()` / `$this->warning()` | | a log line against the rule |
| `$this->error('…')` | `FAIL`, error logged | the next rule runs (see `stopOnError()`) |
| `$this->fatal('…')` | `FATAL_FAIL`, fatal logged | the hub ends (see `stopOnFatal()`) |
| any other exception | `FAIL`, error logged | the next rule runs, unless the rule called `$this->withoutErrorHandling()` |

The result is stored on the envelope under the rule's class, so later rules and `then()` callbacks can read it with `$click->get(MatchIpBlacklist::class)`. Set `public static $saveResultAs = 'ip_verdict';` to also store it under a name of your choosing, and `protected $errorsAreFatal = true;` to make a rule's errors fatal. Inside a rule, `$this->setBotScoreResult(0.4)` / `$this->getBotScoreResult()` read and write `bot_score` on the envelope.

A hub also takes closures, which run, are timed and are logged exactly like rules (under a key such as `Closure@HandleAdClick.php:42`), and then the chain carries on:

```php
->addPipes([
    ResolveVisitorCountry::class,
    fn ($click) => $click->set('region', Regions::for($click->country)),
    EnforceCampaignCountries::class,
])
```

`addPipe()` throws a `PipeHubException` for a class that does not exist or is not a `PipeRule`, so a typo can never silently drop a rule from a flow.

## The log is a timeline

Every record has a time, a key (the rule, or the hub/manager), a type and its data:

```php
$t0 = $click->logs()[0]['time'];

foreach ($click->logs() as $record) {
    printf("%8.3f ms  %-11s %-32s %s\n",
        ($record['time'] - $t0) * 1000, $record['type'], $record['key'], $record['data']);
}
```

```
   0.000 ms  info        PipeManager:sendIt               Sending subject through all hubs
   0.536 ms  info        PipeHub:sendIt                   Sending subject through all pipes
   0.686 ms  timerStart  RequireTrackingParameters        Executing rule RequireTrackingParameters
   0.877 ms  returnValue RequireTrackingParameters        1
   0.880 ms  timerEnd    RequireTrackingParameters        Finished executing rule RequireTrackingParameters
   0.884 ms  timerStart  VerifyClickSignature             Executing rule VerifyClickSignature
   1.253 ms  returnValue VerifyClickSignature             1
   1.256 ms  timerEnd    VerifyClickSignature             Finished executing rule VerifyClickSignature
   1.261 ms  info        PipeHub:sendIt                   Finalized result!
   1.293 ms  info        PipeHub:sendIt                   Sending subject through all pipes
   1.306 ms  timerStart  ResolveVisitorCountry            Executing rule ResolveVisitorCountry
   1.325 ms  info        ResolveVisitorCountry            Resolved 203.0.113.9 to CA
   1.327 ms  returnValue ResolveVisitorCountry            1
   1.328 ms  timerEnd    ResolveVisitorCountry            Finished executing rule ResolveVisitorCountry
   1.329 ms  timerStart  EnforceCampaignCountries         Executing rule EnforceCampaignCountries
   1.397 ms  returnValue EnforceCampaignCountries         3
   1.402 ms  fatal       EnforceCampaignCountries         Visitor is in CA; campaign targets US
   1.412 ms  timerEnd    EnforceCampaignCountries         Finished executing rule EnforceCampaignCountries
   1.416 ms  warning     PipeHub:sendIt                   Stopped by a fatal: Visitor is in CA; campaign targets US. The rest of this hub does not run.
   1.416 ms  info        PipeHub:sendIt                   Finalized result!
   1.436 ms  info        PipeHub:sendIt                   Sending subject through all pipes
   1.437 ms  info        PipeHub:sendIt                   Skipping every rule: skipIf() returned true
   1.441 ms  timerStart  CheckAdvertiserSuppressionList   Executing rule CheckAdvertiserSuppressionList
   1.442 ms  info        CheckAdvertiserSuppressionList   Skipped: the hub's skipIf() returned true
   1.442 ms  returnValue CheckAdvertiserSuppressionList   4
   1.443 ms  timerEnd    CheckAdvertiserSuppressionList   Finished executing rule CheckAdvertiserSuppressionList
   1.445 ms  info        PipeHub:sendIt                   Finalized result!
   ...
   1.469 ms  info        PipeManager:sendIt               Finalized result!
```

(Fatal and error messages also carry the file and line they were raised on, trimmed above.) Every value is made JSON-safe as it is recorded, so persisting a log can never fail and hide what the flow did: a rule may return anything, but NAN and INF are logged as the strings `"NAN"` / `"INF"`, resources as `"resource(stream)"`, closures as `"Closure"`, enums as their value, dates as ISO 8601 strings, exceptions as their class and message, other objects as their public properties plus a `__class` key (or what their `jsonSerialize()`, `toArray()` or `__toString()` gives), cycles as `"[recursion: Class]"`, nesting past 16 levels as `"[max depth]"`, and invalid UTF-8 bytes are replaced. The raw value is still what `$click->get(Rule::class)` and `$click->results()` return. Hub records carry the hub's name and description in their `context`. The logger can also hand you slices: `errors()`, `fatals()`, `warnings()`, `timers()`, `returnValues()`, `recordsByKey($rule)`. `$click->hasErrors()` and `$click->hasFatalErrors()` answer the common question, and `$click->results()` holds every rule's result (`failures()`, `fatalFailures()`, `skips()`, `successes()`).

## Persisting the log

A flow is persisted **once**, when it ends, whether it completed, stopped on a fatal, redirected or threw. Hubs inside a `PipeManager` never persist on their own; a hub run on its own (`$hub->sendIt($subject)`) persists when it finishes. Turn either off with `->persistLogs(false)` and call `$envelope->persist()` yourself, as the example above does.

The strategy is picked in this order: the one given to `$envelope->persistWith()`, then whatever is bound to `PipeLoggerPersistStrategy` in the container. With neither, nothing is written.

### To the database

Publish and run the migration:

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

It creates `pipe_dream_logs`: `run_id` (a unique uuid), `flow` (the manager's name), a nullable `subject_type` / `subject_id` pair (filled in when the subject is a saved Eloquent model; `subject_id` is a string, so integer, UUID and ULID keys all work), `log`, `has_errors`, `has_fatals` and timestamps.

```php
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

// for one flow
$click->persistWith(new DatabasePersistStrategy());

// or for every flow, in a service provider
$this->app->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);
```

`DatabasePersistStrategy` upserts on the envelope's `runId()`, so persisting the same run again updates its row instead of adding one. The log column is JSON by default:

```json
[{"time":1762372800.4121,"key":"PipeManager:sendIt","type":"info","data":"Sending subject through all hubs","context":{"name":"Ad click intake","description":null}},
 {"time":1762372800.4127,"key":"App\\Intake\\Rules\\EnforceCampaignCountries","type":"fatal","data":"Visitor is in CA; campaign targets US [/app/Intake/Rules/EnforceCampaignCountries.php | line 24]","context":{"exception":"Keaton\\PipeDream\\Exceptions\\PipeRuleFatal"}}]
```

Pass `new DatabasePersistStrategy(new PipeLoggerSerializeStrategy())` to store PHP `serialize()` output instead (rebuilt with `allowed_classes => false`, so a stored log can never instantiate objects), or your own `PipeLoggerStrategy`.

Rebuild any stored run into a logger for a timeline view:

```php
use Keaton\PipeDream\Models\PipeDreamLog;

$run = PipeDreamLog::forRun($runId)->firstOrFail();

$run->subject;                    // the Eloquent model the flow ran for, if any
$run->logger()->records();        // the same records the envelope held
$run->logger()->fatals();

PipeDreamLog::withProblems()->latest()->paginate();   // runs with errors or fatals
```

### Anywhere else

Implement one method:

```php
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\PipeEnvelope;

class JsonFileLogStore implements PipeLoggerPersistStrategy
{
    public function persist(PipeEnvelope $subject)
    {
        return file_put_contents(
            storage_path("pipe-logs/{$subject->runId()}.json"),
            $subject->logger()->output()          // JSON unless told otherwise
        );
    }
}
```

## Skipping

| | What runs | What is logged | `then()` |
|---|---|---|---|
| `->skipIf(fn ($s) => …)` returns true | no rule executes | every rule, as `SKIP` with the reason | runs |
| `->skip()` | nothing in the hub | one "Skipping hub" line | does not run |
| `$envelope->skipAll()` (from a rule or callback) | nothing after that point, in this hub or any later hub | every remaining rule, as `SKIP` | runs |
| a rule returns `$this->skip('…')` | the rule ran and decided to skip | that rule, as `SKIP` with the reason | runs |

`skipIf()` keeps the hub's shape in the log: an auditor can see that suppression was checked for and deliberately skipped for this click, rule by rule.

## Stopping

| On the hub | A rule calls `fatal()` | A rule calls `error()` | An exception escapes a rule |
|---|---|---|---|
| (default) | the hub ends: its remaining rules and `then()` are skipped, the next hub runs | logged as `FAIL`, the hub carries on | the hub ends as a failure, `onFailure()` runs, the next hub runs |
| `->stopOnFatal()` | **the whole flow ends**: no later hub, and the manager's `then()` does not run | as default | as default |
| `->stopOnError()` | as default | the hub ends as a failure, `onFailure()` runs, the next hub runs | as default |
| `->failureIsFatal()` | as default | as default (or the flow ends, with `stopOnError()`) | `onFailure()` runs, a fatal is logged and **the whole flow ends** |

Only `withoutErrorHandling()` lets an ordinary exception escape a rule; otherwise it is logged as a `FAIL` and the rule's hub carries on. `onFailure(fn ($subject, $exception) => …)` receives the exception as `$exception`. When the flow ends early, `sendIt()` still returns the envelope (nothing is thrown at you) and the log is still persisted: check `$click->hasFatalErrors()`.

A rule that throws `PipeRuleRedirect` (`PipeRuleRedirect::toUrl($url)` / `::toRoute($name)`) or `PipeRuleLoadView` (`PipeRuleLoadView::load($view, $data)`) is never swallowed: the exception propagates out of the hub and the manager, after the log is persisted, so your controller can act on it.

## Events

```php
PipeHub::create()
    ->name('Bot checks')
    ->broadcastEventBefore(BotChecksStarted::class)          // before the first rule
    ->broadcastEventAfter(BotChecksFinished::class)          // after the last rule, before then()
    ->broadcastEventOnSkip(BotChecksSkipped::class)          // skip() or skipIf()
    ->broadcastEventBeforeEachPipe(RuleStarting::class)      // just before each rule runs
    ->broadcastEventAfterEachPipe(RuleFinished::class);      // as each rule hands off to the next
```

Events are dispatched through Laravel's event dispatcher in the order above: `before`, then `beforeEach`/`afterEach` around each rule in turn, then `after`. Hub events are constructed with the envelope; per-rule events with the envelope and the rule's key:

```php
class RuleFinished
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}
```

A rule that stops the hub never hands off, so its `afterEach` does not fire; rules that are skipped fire neither. Every dispatch is written to the log under `PipeHub:event`.

## Log levels

```php
use Keaton\PipeDream\PipeLogger;

$click->logLevel(PipeLogger::LEVEL_EVERYTHING);   // default
$click->logLevel(PipeLogger::LEVEL_NO_TIMERS);    // drop rule start/end timers
$click->logLevel(PipeLogger::LEVEL_PROBLEMS);     // only warnings, errors and fatals
```

Records below the level are never written, so a busy flow can keep a small log. Rule results (`$click->results()`, `$click->get(Rule::class)`) are recorded whatever the level.

## Introspecting a flow

Tools can read a flow back without reflection, which is how the demo renders its flow page from the same objects that process the clicks: `PipeManager::getName()`, `getDescription()`, `getHubs()`; `PipeHub::getName()`, `getDescription()`, `getPipes()`, `getSkipIf()`, `getThen()`, `getOnFailure()`, `getEvents()`, `stopsOnFatal()`, `stopsOnError()`, `isFailureFatal()`, `isSkipped()`.

## Install

```bash
composer require keaton-php/pipe-dream
```

The service provider is auto-discovered. Publish the migration only if you use the database strategy (see above).

Tested on Laravel 9–13 (CI matrix) with PHP 8.1–8.4, each Laravel major on the PHP versions it supports: Laravel 9 on 8.1–8.2, 10 on 8.1–8.3, 11 and 12 on 8.2–8.4, 13 on 8.3–8.4.

## Testing

```bash
composer install
composer test
```

The suite uses Orchestra Testbench, and its database tests run on in-memory SQLite.

## License

MIT. See [LICENSE](LICENSE).
