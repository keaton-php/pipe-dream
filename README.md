# Pipe Dream

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Tests](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml/badge.svg?branch=1.x)](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/keaton-php/pipe-dream.svg?style=flat-square)](LICENSE)

Pipe Dream turns your business logic into a flow you can read, and every run into a receipt you can read back: every step named, timed and recorded.

---

> **"Why was this click dropped?"**

In most codebases, the honest answer is *"let me get an engineer to dig through the logs."*

With Pipe Dream, the answer is one readable receipt. Here is a click that was accepted:

<!-- output: docs/examples/scripts/click-intake-accepted.php -->
```text
Click intake · Recorded (4.52 ms)

Accept the click — Is this a real click we can trace back to an ad?
  ✓ RequireClickId          (0.05 ms)
  ✓ ParseUserAgent          (0.11 ms)  Chrome 124 on Windows
  ✓ ResolveGeoIp            (1.58 ms)  US, Austin TX

Filter the junk — Does this look like a person, or a script?
  ✓ IsKnownBot              (0.03 ms)
  ✓ DatacenterIpCheck       (0.04 ms)
  ✓ HeadlessBrowserSignals  (0.02 ms)
  ✓ RateLimitPerFingerprint (0.02 ms)

Attribute — Which campaign gets the credit?
  ✓ MatchToCampaign         (0.31 ms)  Spring Sale · Search
  ✓ DeduplicateWithinWindow (0.12 ms)  First click in the window
  ✓ AssignLastTouch         (0.06 ms)  Credit: Spring Sale · Search

Record & notify — Write the row, update the counters, tell the advertiser
  ✓ WriteClickRow           (0.84 ms)  Row written
  ✓ UpdateCampaignCounters  (0.22 ms)  Clicks +1
  ✓ FirePostback            (1.07 ms)  Advertiser notified, HTTP 200
```

Every rule that ran. What it decided. Why. How long it took. A click that gets dropped prints the same receipt: a ✗ on the rule that said no, a reason on the line, and every step after it marked skipped, so nobody has to guess where it stopped, or whether the advertiser was ever told.

You didn't write a single log line to get that. You wrote this:

<!-- example: docs/examples/scripts/click-intake.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$envelope = PipeManager::create([

    PipeHub::create()
        ->name('Accept the click')
        ->description('Is this a real click we can trace back to an ad?')
        ->addPipes([
            RequireClickId::class,
            ParseUserAgent::class,
            ResolveGeoIp::class,
        ]),                           // no click id, no click: the flow ends here

    PipeHub::create()
        ->name('Filter the junk')
        ->description('Does this look like a person, or a script?')
        ->addPipes([
            IsKnownBot::class,
            DatacenterIpCheck::class,
            HeadlessBrowserSignals::class,
            RateLimitPerFingerprint::class,
        ])
        ->skipIf(fn ($click) => $click->fromAllowlistedPartner(), 'Allowlisted partner')
        ->then(fn ($click) => $click->botSignals() >= 3 ? $click->drop("{$click->botSignals()} automation signals") : null),

    PipeHub::create()
        ->name('Attribute')
        ->description('Which campaign gets the credit?')
        ->addPipes([
            MatchToCampaign::class,
            DeduplicateWithinWindow::class,
            AssignLastTouch::class,
        ])
        ->skipIf(fn ($click) => $click->isDropped() ? "Dropped: {$click->dropReason}" : null),

    PipeHub::create()
        ->name('Record & notify')
        ->description('Write the row, update the counters, tell the advertiser')
        ->addPipes([
            WriteClickRow::class,
            UpdateCampaignCounters::class,
            FirePostback::class,
        ])
        ->skipIf(fn ($click) => $click->isDropped() ? "Dropped: {$click->dropReason}" : null),

])
    ->name('Click intake')
    ->subject($click)
    ->then(fn ($click) => $click->settle())
    ->sendIt();
```

Read it once and you understand the whole intake pipeline. So will the next engineer, your support lead, and you in six months. The receipt is `echo $envelope->receipt()`; the same record is also there as data, through `$envelope->steps()` and `$envelope->logs()`.

## Same shape, any business

Click intake is one flow. Here's a completely different one: a checkout, where the question is *"why was my order put on hold?"*. Different business, same pipe, same receipt:

<!-- output: docs/examples/scripts/checkout-held.php -->
```text
Checkout · On hold: Two or more fraud signals (2.58 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.06 ms)
  ✓ ConfirmItemsInStock           (1.75 ms)  3 items in stock
  ✓ ApplyDiscountCodes            (0.04 ms)  SPRING20 applied: -$24.00
  ✓ CalculateTax                  (0.02 ms)  Tax: $7.92

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

The card was never charged and nothing shipped, so your support team can say so, with receipts. The [checkout walkthrough](docs/16-checkout-walkthrough.md) builds this one step by step, and the click-intake pattern has pushed hundreds of millions of clicks through production with a full audit trail on every one. When something looked wrong, nobody stepped through code. They read the receipt.

Loan decisions, KYC onboarding, insurance claims, refunds, content moderation: **if it's a series of decisions someone will later ask you to explain, it's a pipe.**

## How it works, in three words

- **Rules:** small classes that do one thing, like `IsKnownBot` or `RequireClickId`. Each one passes, fails, skips, or stops the flow, and gives a reason.
- **Hubs:** named stages that group rules, with control flow declared right on the stage: `skipIf()`, `then()`, `errorsAreFatal()`, `fatalsStopThisHubOnly()`.
- **The receipt:** every run is timed and logged automatically, then saved as one row you can query (`PipeDreamLog::withProblems()`) and rebuild into the timelines above, long after the request is gone.

## Try it

```bash
composer require keaton-php/pipe-dream
```

PHP 8.1+, Laravel 10–13. The service provider is auto-discovered.

**→ [Build your first flow in five minutes](https://kevineaton.dev/docs/pipe-dream/your-first-flow/)** · [Docs](https://kevineaton.dev/docs/pipe-dream/) · [The story behind it](https://kevineaton.dev/writing/pipe-driven-development/)

MIT licensed. Built by [Kevin Eaton](https://kevineaton.dev).
