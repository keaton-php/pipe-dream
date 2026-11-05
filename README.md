# Pipe Dream

**Pipe-driven development for Laravel.** You write a business flow as named stages of small rules, and it reads like a table of contents. Every rule that runs is logged, timed and recorded with its result, so when someone asks *"why did this request end up here?"*, you open the log instead of the debugger.

It's built on Laravel's own `Pipeline`, and it has run a high-volume production click-intake system: every incoming click goes through about ten stages and dozens of rules, with a full audit trail per click.

```php
use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeManager;
use R1kon\PipeDream\PipeEnvelope;

$signup = PipeEnvelope::wrap($signupRequest);

PipeManager::create([

    PipeHub::create()
        ->name('Validate')
        ->description('Is this a well-formed signup?')
        ->addPipes([
            RequireEmail::class,
            RejectDisposableDomains::class,
        ])
        ->stopOnFatal(),                 // a fatal here ends the whole flow

    PipeHub::create()
        ->name('Fraud screen')
        ->description('Does this look like a real person?')
        ->skipIf(fn ($s) => $s->isTrustedPartner())
        ->addPipes([
            CheckIpReputation::class,
            CheckVelocity::class,
        ])
        ->then(fn ($s) => $s->get(CheckVelocity::class) === CheckVelocity::FAIL
            ? $s->flag('Too many signups from this IP')
            : null),

    PipeHub::create()
        ->name('Onboard')
        ->addPipes([
            CreateAccount::class,
            SendWelcomeEmail::class,
        ]),

])->subject($signup)->sendIt();

$signup->logs();        // every step, in order, with timings
$signup->hasErrors();   // did anything go wrong along the way?
```

## See it running

A demo app pushes 500 synthetic ad clicks through a five-stage, 14-rule flow. Every page below is built from the flow definition and the persisted pipe logs, with nothing written by hand.

| The flow, rendered from the code | One click's full story |
|---|---|
| ![The flow](docs/01-flow.png) | ![One click's story](docs/03-click-story.png) |
| **Dashboard built from the logs** | **The whole flow is one readable file** |
| ![Dashboard](docs/02-dashboard.png) | ![Source](docs/04-code.png) |

## A rule is one small class

```php
use R1kon\PipeDream\PipeRule;

class RejectDisposableDomains extends PipeRule
{
    public static $name = 'Reject disposable domains';
    public static $description = 'Throwaway inboxes never become customers';

    // The method named after the class runs; dependencies are injected by the container.
    public function rejectDisposableDomains($subject, DisposableDomains $list)
    {
        if ($list->contains($subject->emailDomain())) {
            $this->fatal('Disposable email domain');   // stops the stage if stopOnFatal()
        }

        return self::SUCCESS;
    }
}
```

Return `SUCCESS`, `FAIL`, `FATAL_FAIL` or `SKIP`, or raise an error or fatal. The result is recorded against the rule, so later stages and `then()` callbacks can read it with `$subject->get(RuleClass::class)`.

## What you get

- **Flows that read like a story.** A stage name and description tell a new engineer, or an auditor, what the code does before they read any of it.
- **Per-stage control:** `stopOnFatal()`, `stopOnError()`, `failureIsFatal()`, `skipIf()`, `then()` and `onFailure()`.
- **A full audit log** of every rule: start/end timers, info / warning / error / fatal entries and return values. Persist it anywhere by implementing `PipeLoggerPersistStrategy` (database, file, queue), and rebuild it later for a timeline view.
- **Events** before and after each stage and each pipe, for metrics and dashboards.
- **Control-flow exits** (`PipeRuleRedirect`, `PipeRuleLoadView`) for when a rule needs to send the user somewhere.
- **The envelope pattern:** the subject travels wrapped, so rules share state (`set()` / `get()`) without bloating your model.

## Install

```bash
composer require r1kon/pipe-dream
```

Tested with Laravel 9 in production. The constraints allow 10–13; run the test suite against your version before relying on it.

## License

MIT
