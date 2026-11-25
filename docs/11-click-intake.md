<!-- nav-group: Walkthroughs -->
# Click intake

The introduction opened with a click intake. This page builds a full one, the way it runs in production. An ad network receives a click from a publisher's site, decides whether the advertiser should pay for it, and sends the visitor on. Every click is saved, so any one of them can be explained later.

## The flow

The controller wraps the click, runs four stages, saves the run once and redirects the visitor:

```php
<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

class HandleAdClick
{
    public function __invoke(Request $request)
    {
        $click = PipeEnvelope::wrap(new IncomingClick($request))
            ->persistWith(new DatabasePersistStrategy);

        try {
            PipeManager::create([
                PipeHub::create()
                    ->name('Validate')
                    ->description('Is this a complete click for a live campaign?')
                    ->addPipes([
                        RequireTrackingParameters::class,
                        RecognizeCampaign::class,
                        ChallengeGraylistedPublishers::class,
                    ]),
                PipeHub::create()
                    ->name('Bot checks')
                    ->description('Does this look like a person?')
                    ->addPipes([
                        DetectHostingProviderIp::class,
                        MatchKnownBotUserAgents::class,
                        ScoreClickVelocity::class,
                    ])
                    ->skipIf(fn ($click) => $click->campaignSettings()->skipBotChecks)
                    ->then(fn ($click) => $click->get('bot_signals') >= 2
                        ? $click->flag('Two or more bot signals')
                        : null),
                PipeHub::create()
                    ->name('Budget')
                    ->description('Daily cap and spend, only for clicks we will bill')
                    ->addPipes([
                        EnforceDailyClickCap::class,
                        RecordBudgetSpend::class,
                    ])
                    ->skipIf(fn ($click) => $click->reasonNotToBill())
                    ->errorsAreFatal()
                    ->fatalsStopThisHubOnly(),
                PipeHub::create()
                    ->name('Route')
                    ->description('Landing page or review queue')
                    ->addPipe(ChooseDestination::class),
            ])
                ->name('Ad click intake')
                ->subject($click)
                ->persistLogs(false)
                ->then(fn ($click) => $click->markComplete())
                ->sendIt();
        } catch (PipeRuleLoadView $e) {
            return view($e->view, $e->data);
        } finally {
            $click->persist();
        }

        return redirect()->to($click->destination());
    }
}
```

## One click's receipt

This click comes from a hosting provider's IP address, with a user agent that names a bot:

```php
use Illuminate\Http\Request;
use Keaton\PipeDream\Models\PipeDreamLog;

$request = Request::create(
    '/click?click_id=clk_201&campaign=spring-sale&pub=pub-12',
    server: [
        'REMOTE_ADDR' => '198.51.100.7',
        'HTTP_USER_AGENT' => 'HeadlessChrome SEO-bot/2.1',
    ],
);

app(HandleAdClick::class)($request);
echo PipeDreamLog::latest('id')->first()->receipt();
```

```text
Ad click intake · completed with errors (0.22 ms)

Validate: Is this a complete click for a live campaign?
  ✓ RequireTrackingParameters     (0.02 ms)
  ✓ RecognizeCampaign             (0.01 ms)
  ✓ ChallengeGraylistedPublishers (0.01 ms)

Bot checks: Does this look like a person?
  ✗ DetectHostingProviderIp       (0.01 ms)  198.51.100.7 belongs to a hosting provider
  ✗ MatchKnownBotUserAgents       (0.01 ms)  User agent looks like a bot
  ✓ ScoreClickVelocity            (0.01 ms)

Budget: Daily cap and spend, only for clicks we will bill · skipped: Flagged, not billed
  ↷ EnforceDailyClickCap          skipped
  ↷ RecordBudgetSpend             skipped

Route: Landing page or review queue
  ✓ ChooseDestination             (0.01 ms)
```

Two bot signals flagged the click, so the Budget stage was skipped and the advertiser was not billed. The visitor was still routed: `ChooseDestination` sends a flagged click to a review page. The checks that passed are on the receipt too, which shows that they ran.

## What is different from checkout

- Budget contains its own failures. It uses `errorsAreFatal()->fatalsStopThisHubOnly()`: a failed budget write ends that stage, and the visitor is still routed. Checkout makes the opposite choice for Charge, where a failure must stop everything.
- The reason for a skip is chosen at run time. Budget's `skipIf()` callback returns the reason itself: `reasonNotToBill()` gives a string or `null`. A stage with several reasons to skip can say which one applied.
- A rule can hand off to a view. A click from a publisher on the graylist gets a challenge page: `ChallengeGraylistedPublishers` calls `PipeRuleLoadView::load()`, the controller returns the view, and the receipt's outcome is `handed off`.
- The run is saved once per request. The envelope is wrapped before the flow starts, so the controller holds it in every branch. `persistLogs(false)` turns off the flow's own save, and the `finally` block saves the run once, whatever happened, including a hand-off.

**Next:** [Checkout walkthrough](12-checkout-walkthrough.md).
