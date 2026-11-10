<!-- nav-group: Walkthroughs -->
# A high-volume click intake

A different business, the same pattern. This is the shape of a click-tracking flow of the kind that has handled hundreds of millions of ad clicks: eight stages, every click saved, every click explainable.

## The flow

Eight stages, from "is this a real click?" to "where does the visitor go?":

<!-- example: docs/examples/ClickIntakeFlow.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class ClickIntakeFlow
{
    public static function build(): PipeManager
    {
        return PipeManager::create([

            PipeHub::create()
                ->name('Validate & start session')
                ->description('Is this a complete, signed click for a live campaign?')
                ->addPipes([
                    RequireTrackingParameters::class,
                    VerifyClickSignature::class,
                    RecognizeCampaign::class,
                    StartClickSession::class,
                ]),                          // a fatal here (a bad click) ends the whole flow

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
                ]),

            PipeHub::create()
                ->name('Suppression')
                ->description('Has this visitor asked the advertiser not to contact them?')
                ->addPipes([
                    CheckAdvertiserSuppressionList::class,
                ])
                ->skipIf(fn ($click) => $click->campaignSettings()->isNewsletter(), 'Newsletter campaign'),

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
                ->skipIf(fn ($click) => $click->isFlagged() || $click->isTest()
                    ? 'Flagged — not charged'
                    : null)
                ->errorsAreFatal()           // a failed charge ends this hub...
                ->fatalsStopThisHubOnly(),   // ...but the click is still routed

            PipeHub::create()
                ->name('Route')
                ->description('Landing page, review queue or a safe dead end')
                ->addPipes([
                    ChooseDestination::class,
                ]),

        ])
            ->name('Ad click intake')
            ->then(fn ($click) => $click->markComplete());
    }
}
```

A controller wraps the click, runs the flow, saves every run, and routes the visitor:

<!-- example: docs/examples/HandleAdClick.php -->
```php
<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

class HandleAdClick
{
    public function __invoke(Request $request)
    {
        $click = PipeEnvelope::wrap(new IncomingClick($request))
            ->persistWith(new DatabasePersistStrategy());

        try {
            ClickIntakeFlow::build()
                ->subject($click)
                ->persistLogs(false)             // saved once, below
                ->sendIt();

        } catch (PipeRuleRedirect $e) {
            return redirect()->to($e->redirectTo);
        } catch (PipeRuleLoadView $e) {
            return view($e->view, $e->data);
        } finally {
            $click->persist();                   // one row per click, whatever happened
        }

        return redirect()->to($click->destination());
    }
}
```

There's no timer in it. The receipt times every step, and the whole run, on its own.

## One click's receipt

A click from a hosting provider's IP, with a bot's user agent:

<!-- example: docs/examples/scripts/click-receipt.php -->
```php
<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Models\PipeDreamLog;

$click = Request::create('/click', 'GET', [
    'click_id' => 'clk_201',
    'campaign' => 'spring-sale',
    'pub' => 'pub-12',
    'country' => 'US',
], [], [], [
    'REMOTE_ADDR' => '198.51.100.7',
    'HTTP_USER_AGENT' => 'HeadlessChrome SEO-bot/2.1',
]);

app(HandleAdClick::class)($click);

echo PipeDreamLog::latest('id')->first()->receipt();
```

<!-- output: docs/examples/scripts/click-receipt.php -->
```text
Ad click intake · Flagged: Two or more bot signals (0.60 ms)

Validate & start session — Is this a complete, signed click for a live campaign?
  ✓ RequireTrackingParameters      (0.06 ms)
  ✓ VerifyClickSignature           (0.01 ms)
  ✓ RecognizeCampaign              (0.02 ms)
  ✓ StartClickSession              (0.01 ms)

Tracking — Attribution cookie and conversion-path tracking
  ✓ SetAttributionCookie           (0.01 ms)
  ✓ RecordConversionPath           (0.01 ms)

Geo exclusions — Is the visitor somewhere this campaign buys traffic?
  ✓ ResolveVisitorCountry          (0.02 ms)  Visitor is in US
  ✓ EnforceCampaignCountries       (0.01 ms)
  ✓ EnforceAdvertiserGeoExclusions (0.01 ms)

Suppression — Has this visitor asked the advertiser not to contact them?
  ✓ CheckAdvertiserSuppressionList (0.02 ms)

Blacklist & graylist — Checks that are never skipped
  ✓ MatchIpBlacklist               (0.01 ms)
  ✓ MatchPublisherBlacklist        (0.01 ms)
  ✓ ChallengeGraylistedPublishers  (0.01 ms)

Bot checks — Does this look like a person, not a script or a server?
  ✗ DetectHostingProviderIp        (0.02 ms)  198.51.100.7 belongs to a hosting provider
  ✗ MatchKnownBotUserAgents        (0.01 ms)  User agent looks like a bot
  ✓ ScoreClickVelocity             (0.01 ms)

Fulfillment & caps — Daily caps and budget, only for clicks we will pay for · skipped: Flagged — not charged
  ↷ EnforceDailyClickCap           skipped
  ↷ RecordBudgetSpend              skipped

Route — Landing page, review queue or a safe dead end
  ✓ ChooseDestination              (0.01 ms)
```

Two bot signals flagged the click, so the Fulfillment stage was skipped: the advertiser was never charged for it, and the click was routed anyway. Every stage is on the receipt, including the checks that passed. That proves they ran.

The header says "Flagged" because `IncomingClick` implements the optional `ReceiptSummary` interface; see [The receipt](07-the-receipt.md).

## A slow click

A tester asks: "why did that take 15 seconds to redirect me?" A status column can't answer that. The receipt can. Here's the same click on a link with no country, on a day the GeoIP provider is struggling:

<!-- example: docs/examples/scripts/click-slow.php -->
```php
<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Models\PipeDreamLog;

GeoIp::$slowLookups = [6.0, 6.0, 4.8];          // the GeoIP provider is having a bad day

$click = Request::create('/click', 'GET', [
    'click_id' => 'clk_202',
    'campaign' => 'spring-sale',
    'pub' => 'pub-12',                          // no country on this link, so we ask GeoIP
], [], [], [
    'REMOTE_ADDR' => '198.51.100.7',
    'HTTP_USER_AGENT' => 'HeadlessChrome SEO-bot/2.1',
]);

app(HandleAdClick::class)($click);

echo PipeDreamLog::latest('id')->first()->receipt();
```

<!-- output: docs/examples/scripts/click-slow.php -->
```text
Ad click intake · Flagged: Two or more bot signals (14.80 s)

Validate & start session — Is this a complete, signed click for a live campaign?
  ✓ RequireTrackingParameters      (0.08 ms)
  ✓ VerifyClickSignature           (0.02 ms)
  ✓ RecognizeCampaign              (0.05 ms)
  ✓ StartClickSession              (0.02 ms)

Tracking — Attribution cookie and conversion-path tracking
  ✓ SetAttributionCookie           (0.01 ms)
  ✓ RecordConversionPath           (0.01 ms)

Geo exclusions — Is the visitor somewhere this campaign buys traffic?
  ✓ ResolveVisitorCountry          (14.80 s)  GeoIP timed out after 5 s (try 1 of 3)
                                              GeoIP timed out after 5 s (try 2 of 3)
                                              Visitor is in US
  ✓ EnforceCampaignCountries       (0.09 ms)
  ✓ EnforceAdvertiserGeoExclusions (0.02 ms)

Suppression — Has this visitor asked the advertiser not to contact them?
  ✓ CheckAdvertiserSuppressionList (0.17 ms)

Blacklist & graylist — Checks that are never skipped
  ✓ MatchIpBlacklist               (0.02 ms)
  ✓ MatchPublisherBlacklist        (0.01 ms)
  ✓ ChallengeGraylistedPublishers  (0.01 ms)

Bot checks — Does this look like a person, not a script or a server?
  ✗ DetectHostingProviderIp        (0.05 ms)  198.51.100.7 belongs to a hosting provider
  ✗ MatchKnownBotUserAgents        (0.02 ms)  User agent looks like a bot
  ✓ ScoreClickVelocity             (0.01 ms)

Fulfillment & caps — Daily caps and budget, only for clicks we will pay for · skipped: Flagged — not charged
  ↷ EnforceDailyClickCap           skipped
  ↷ RecordBudgetSpend              skipped

Route — Landing page, review queue or a safe dead end
  ✓ ChooseDestination              (0.01 ms)
```

One line holds the answer: `ResolveVisitorCountry` took 14.80 s, because GeoIP timed out twice before it answered. Every other step took a fraction of a millisecond. Nobody profiled anything; the rule only said what it was doing (`$this->warning(...)`), and Pipe Dream timed it.

Every saved run carries these times, step by step, so "which step is slow this week?" is a query over runs you already have, not new instrumentation. `GeoIp` here is a stand-in that really waits; the times above are from a real run.

## What's different from checkout

- **Volume.** Each click is one request and one saved row. The flow itself adds a fraction of a millisecond.
- **Containment.** Fulfillment uses `errorsAreFatal()->fatalsStopThisHubOnly()`: a failed budget charge ends that stage, but the visitor is still routed. Checkout makes the opposite choice for Charge, and both are one readable line.
- **Never-skipped checks.** The blacklist stage has no `skipIf()`, and its description says so.

**Next:** [Outcome tables](18-outcome-tables.md).
