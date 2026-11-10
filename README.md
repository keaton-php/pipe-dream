# Pipe Dream

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Tests](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml/badge.svg?branch=1.x)](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/keaton-php/pipe-dream.svg?style=flat-square)](LICENSE)

Pipe Dream turns your business logic into a flow you can read, and every run into a receipt you can read back: every step named, timed and recorded.

---

> **"Why was my click flagged, and why did it take 15 seconds to redirect me?"**

Our tester clicked a tracking link and asked exactly that. The `status` column could answer the first half: `flagged`. Nothing could answer the second half without an engineer, a profiler and an afternoon.

With Pipe Dream, both answers are in one database row:

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

- **Why it was flagged:** two bot checks failed, a hosting provider's IP and a bot's user agent, so the advertiser was never charged for it.
- **Why it was slow:** `ResolveVisitorCountry` spent 14.80 s on a GeoIP provider that timed out twice. Every other step took a fraction of a millisecond.

Nobody added a timer or wrote a log line to get that. Every rule is named, timed and recorded on every run, so the answer is already there when someone asks.

## A status tells you where it ended up. A receipt tells you how it got there.

And how long every step took. Every run is saved as one row, step by step, so "which step got slow this week?" is a query over rows you already have, and a dashboard is a page that reads them. There's no instrumentation to add and no list of steps to keep in sync: the flow *is* the list of steps.

## The flow reads like a book

The receipt above came from this:

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
                ->skipIf(fn ($click) => $click->campaignSettings()->isNewsletter()),

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

Read it once and you know the whole intake. So will the next engineer, your support lead, and you in six months.

That pattern has pushed hundreds of millions of clicks through production, with a receipt for every one. When something looked wrong, or slow, nobody stepped through code. They read the receipt.

## Same shape, any business

Here's a checkout. Different business, same pipe, same receipt:

<!-- output: docs/examples/scripts/checkout-held.php -->
```text
Checkout · On hold: Two or more fraud signals (2.47 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.04 ms)
  ✓ ConfirmItemsInStock           (2.11 ms)  3 items in stock
  ✓ ApplyDiscountCodes            (0.04 ms)  SPRING20 applied: -$24.00
  ✓ CalculateTax                  (0.01 ms)  Tax: $7.92

Fraud screen — Does this look like the real cardholder?
  ✗ MatchBillingToShippingCountry (0.02 ms)  Billing US, shipping NL
  ✗ CheckCardVelocity             (0.01 ms)  4 cards tried on this account in 10 minutes
  ✓ ScoreDeviceFingerprint        (0.01 ms)

Charge — Take the money, only for orders we will ship · skipped: Held for review: Two or more fraud signals
  ↷ AuthorizePayment              skipped
  ↷ CapturePayment                skipped

Fulfil — Reserve stock, tell the warehouse, email the receipt · skipped: Held for review: Two or more fraud signals
  ↷ ReserveInventory              skipped
  ↷ SendToWarehouse               skipped
  ↷ EmailReceipt                  skipped
```

The card was never charged and nothing shipped, and the receipt proves it.

Loan decisions, KYC onboarding, insurance claims, refunds, content moderation: **if it's a series of decisions someone will later ask you to explain, or ask why it was slow, it's a pipe.**

## How it works, in three words

- **Rules:** small classes that do one thing, like `CheckCardVelocity` or `VerifyClickSignature`. Each one passes, fails, skips, or stops the flow, and gives a reason.
- **Hubs:** named stages that group rules, with control flow declared right on the stage: `skipIf()`, `then()`, `errorsAreFatal()`, `fatalsStopThisHubOnly()`.
- **The receipt:** every run is timed and logged automatically, then saved as one row you can query (`PipeDreamLog::withProblems()`) and rebuild into the timelines above, long after the request is gone.

## Try it

```bash
composer require keaton-php/pipe-dream
```

PHP 8.1+, Laravel 9–13. The service provider is auto-discovered.

**→ [Build your first flow in five minutes](https://kevineaton.dev/docs/pipe-dream/your-first-flow/)** · [Docs](https://kevineaton.dev/docs/pipe-dream/) · [The story behind it](https://kevineaton.dev/writing/pipe-driven-development/)

MIT licensed. Built by [Kevin Eaton](https://kevineaton.dev).
