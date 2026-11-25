# Pipe Dream

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Tests](https://img.shields.io/github/actions/workflow/status/keaton-php/pipe-dream/run-tests.yml?branch=1.x&label=tests&style=flat-square)](https://github.com/keaton-php/pipe-dream/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)

Pipe Dream turns your business logic into a flow you can read, and every run into a receipt you can read back: every step named, timed and recorded.

Code for something as complex as tracking email clicks gets complicated fast. It spreads across files, it is hard to read, and it is even harder to question. Ask "why did this request take so long?" or "why wasn't this click accepted?" and the honest answer is usually "let me get an engineer to dig through the logs."

Pipe Dream makes both of those simple. The code reads top to bottom, and every run answers for itself. Here is a click that was accepted:

```text
Click intake · Recorded (4.52 ms)

Accept the click: Is this a real click we can trace back to an ad?
  ✓ RequireClickId          (0.05 ms)
  ✓ ParseUserAgent          (0.11 ms)  Chrome 124 on Windows
  ✓ ResolveGeoIp            (1.58 ms)  US, Austin TX

Filter the junk: Does this look like a person, or a script?
  ✓ IsKnownBot              (0.03 ms)
  ✓ DatacenterIpCheck       (0.04 ms)
  ✓ HeadlessBrowserSignals  (0.02 ms)
  ✓ RateLimitPerFingerprint (0.02 ms)

Attribute: Which campaign gets the credit?
  ✓ MatchToCampaign         (0.31 ms)  Spring Sale · Search
  ✓ DeduplicateWithinWindow (0.12 ms)  First click in the window
  ✓ AssignLastTouch         (0.06 ms)  Credit: Spring Sale · Search

Record & notify: Write the row, update the counters, tell the advertiser
  ✓ WriteClickRow           (0.84 ms)  Row written
  ✓ UpdateCampaignCounters  (0.22 ms)  Clicks +1
  ✓ FirePostback            (1.07 ms)  Advertiser notified, HTTP 200
```

The receipt lists every rule that ran, what it decided and why, and how long it took. A dropped click gets the same receipt. The rule that said no is marked with a ✗ and its reason, and every step after it is marked as skipped. Nobody has to guess where it stopped, or whether the advertiser was told.

You didn't write a single log line to get that. You wrote this:

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

The whole intake reads top to bottom. Someone new to the code can follow it on the first pass, and so can you when you come back to it months later.

`$envelope->receipt()` gives you the receipt, and the same record is there as data through `$envelope->steps()` and `$envelope->logs()`. Turn on saving once, and every run is stored, ready to pull back later on its own or as part of a group.

## Every run is a row

Every saved run is one row in one table. The row holds the flow's name and what it ran on, whether anything went wrong, and every step with how long it took. Getting runs back is one query:

```php
use Keaton\PipeDream\Models\PipeDreamLog;

// One run, by its id
$receipt = PipeDreamLog::forRun($runId)->first()->receipt();

// Every click intake that had a problem today
$runs = PipeDreamLog::where('flow', 'Click intake')
    ->withProblems()
    ->whereDate('created_at', today())
    ->get();
```

Because the shape is so simple, a dashboard is easy to write: a few queries and a view.

As an example, this page was built from one saved click, and nothing else:

<img src="docs/click-story.png" alt="One click's full story, rebuilt from its saved run" width="100%">

## Same shape, any business

Click intake is one flow. Here's a completely different one: a checkout, where the question is "why was my order put on hold?" Different business, same pipe, same receipt:

```text
Checkout · On hold: Two or more fraud signals (2.43 ms)

Validate the order: Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.14 ms)
  ✓ ConfirmItemsInStock           (1.58 ms)  3 items in stock
  ✓ ApplyDiscountCodes            (0.04 ms)  SPRING20 applied: -$24.00
  ✓ CalculateTax                  (0.01 ms)  Tax: $7.92

Fraud screen: Does this look like the real cardholder?
  ✗ MatchBillingToShippingCountry (0.02 ms)  Billing US, shipping NL
  ✗ CheckCardVelocity             (0.01 ms)  4 cards tried on this account in 10 minutes
  ✓ ScoreDeviceFingerprint        (0.01 ms)

Charge: Take the money, only for orders we will ship · skipped: Order is on hold
  ↷ AuthorizePayment              skipped
  ↷ CapturePayment                skipped

Fulfill: Reserve stock, tell the warehouse, email the receipt · skipped: Order is on hold
  ↷ ReserveInventory              skipped
  ↷ SendToWarehouse               skipped
  ↷ EmailReceipt                  skipped
```

The card was never charged and nothing shipped, so your support team can say so, with receipts. The [checkout walkthrough](docs/12-checkout-walkthrough.md) builds this one step by step.

Loan decisions, KYC onboarding, insurance claims, refunds, content moderation: if it's a series of decisions someone will later ask you to explain, it's a pipe.

## What else it does

- A rule can pass, flag a concern, skip itself or stop the flow, and each stage sets how far a stop reaches.
- A rule is a plain class, and Laravel's container fills in what it asks for, so you can test one rule alone or the whole flow.
- Runs can be saved as one database row each, and retrieved later to build the same receipt.
- The receipt comes back as text or Markdown, or as an array for an API, and it can answer questions in code.
- A rule can send the visitor to another page or show a view, and your controller decides what to do with it.
- A stage can fire your own Laravel events as it runs.
- The code reads so plainly that it almost documents itself. A flow can even be read without running it, to draw the process for a docs page.

The package is not a queue or a workflow engine. A flow runs in order, in one PHP process, inside your controller, job or command.

## Installation

You can install the package via composer:

```bash
composer require keaton-php/pipe-dream
```

The package requires PHP 8.1 or higher and Laravel 10, 11, 12 or 13.

To save runs, you can publish and run the migration with:

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

## Documentation

You'll find the documentation on [kevineaton.dev/docs/pipe-dream](https://kevineaton.dev/docs/pipe-dream/). It starts with [Your first flow](https://kevineaton.dev/docs/pipe-dream/your-first-flow/), which builds the checkout step by step, and [Click intake](https://kevineaton.dev/docs/pipe-dream/click-intake/) builds a full click flow. The same pages are in the [docs](docs) folder of this repository.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Kevin Eaton](https://github.com/keaton-php)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
