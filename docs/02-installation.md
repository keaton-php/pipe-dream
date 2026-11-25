<!-- nav-group: Start here -->
# Installation

One Composer command installs the package. The database table is optional. You need it only to keep receipts after the request ends.

## Requirements

Pipe Dream needs PHP 8.1 or newer and Laravel 10 to 13. The test suite runs on each Laravel version with the PHP versions that Laravel version supports:

| Laravel | PHP |
|---|---|
| 10 | 8.1 to 8.3 |
| 11 | 8.2 to 8.4 |
| 12 | 8.2 to 8.5 |
| 13 | 8.3 to 8.5 |

## Install

```bash
composer require keaton-php/pipe-dream
```

The service provider is discovered automatically. There is no config file to publish. You can now build flows, run them and print their receipts.

## Check that it works

The quickest proof is a test. [Your first flow](03-your-first-flow.md) builds a checkout, and [Testing flows](10-testing.md) turns its receipt into assertions that run with the rest of your suite:

```bash
php artisan test
```

A passing run shows that the package is installed and that your flow does what you expect.

## Optional: keep every run

To save runs and print their receipts later, publish the migration and run it. It creates one table, `pipe_dream_logs`, with one row per run:

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

Then turn saving on once, in a service provider. [Saving and replaying runs](09-saving-and-replaying.md) shows how, and what the row holds.

**Next:** [Your first flow](03-your-first-flow.md).
