# Pipe Dream

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Tests](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml/badge.svg?branch=1.x)](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/keaton-php/pipe-dream.svg?style=flat-square)](LICENSE)

Pipe Dream turns your business logic into a flow you can read, and every run into a receipt you can read back: every step named, timed and recorded.

---

> **"Why was my order put on hold?"**

In most codebases, the honest answer is *"let me get an engineer to dig through the logs."*

With Pipe Dream, the answer is one database row:

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

Every rule that ran. What it decided. Why. How long it took. And proof of what *didn't* happen: the card was never charged and nothing shipped, so your support team can say so, with receipts.

You didn't write a single log line to get that. You wrote this:

<!-- example: docs/examples/scripts/checkout.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$envelope = PipeManager::create([

    PipeHub::create()
        ->name('Validate the order')
        ->description('Is this a complete order for items we can actually ship?')
        ->addPipes([
            RequireShippingAddress::class,
            ConfirmItemsInStock::class,
            ApplyDiscountCodes::class,
            CalculateTax::class,
        ]),                           // a broken order ends the whole flow here

    PipeHub::create()
        ->name('Fraud screen')
        ->description('Does this look like the real cardholder?')
        ->addPipes([
            MatchBillingToShippingCountry::class,
            CheckCardVelocity::class,
            ScoreDeviceFingerprint::class,
        ])
        ->skipIf(fn ($order) => $order->customerIsTrusted(), 'Trusted customer')
        ->then(fn ($order) => $order->riskSignals() >= 2 ? $order->holdForReview('Two or more fraud signals') : null),

    PipeHub::create()
        ->name('Charge')
        ->description('Take the money, only for orders we will ship')
        ->addPipes([
            AuthorizePayment::class,
            CapturePayment::class,
        ])
        ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null)
        ->errorsAreFatal(),           // a failed charge ends the flow

    PipeHub::create()
        ->name('Fulfil')
        ->description('Reserve stock, tell the warehouse, email the receipt')
        ->addPipes([
            ReserveInventory::class,
            SendToWarehouse::class,
            EmailReceipt::class,
        ])
        ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null),

])
    ->name('Checkout')
    ->subject($order)
    ->then(fn ($order) => $order->markPlaced())
    ->sendIt();
```

Read it once and you understand the whole checkout process. So will the next engineer, your support lead, and you in six months.

## Same shape, any business

Checkout is one flow. Here's a completely different one: a production ad-click intake, the kind that sits in front of a high-volume tracking system. Different business, same pipe, same receipt:

```text
Ad click intake · completed with errors (1.40 ms)

Validate & start session
  ✓ RequireTrackingParameters      (0.02 ms)
  ✓ VerifyClickSignature           (0.01 ms)
  ✓ RecognizeCampaign              (0.01 ms)  Campaign quillstone-free-plan

Suppression · skipped: Newsletter campaign
  ↷ CheckAdvertiserSuppressionList skipped

Bot checks
  ✗ DetectHostingProviderIp        (0.14 ms)  203.0.113.23 belongs to Example Cloud
  ✗ ScoreClickVelocity             (0.02 ms)  4 clicks from this IP in a minute

Route
  ✓ ChooseDestination              (0.01 ms)  Held for review: two bot signals
```

That pattern has pushed hundreds of millions of clicks through production, with a full audit trail on every one. When something looked wrong, nobody stepped through code. They read the receipt.

Loan decisions, KYC onboarding, insurance claims, refunds, content moderation: **if it's a series of decisions someone will later ask you to explain, it's a pipe.**

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
