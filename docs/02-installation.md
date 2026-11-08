<!-- nav-group: Start here -->
# Installation

One Composer command. The database table is optional, and only needed when you want to keep receipts after the request ends.

## Requirements

- PHP 8.1 or newer
- Laravel 9 to 13 (the full matrix is on [Compatibility](20-compatibility.md))

## Install

```bash
composer require keaton-php/pipe-dream
```

The service provider is discovered automatically. That's enough to build and run flows and to print their receipts.

## Check it works

The quickest proof is a test. [Your first flow](03-your-first-flow.md) builds a checkout and sends three orders through it, and [Testing flows](13-testing.md) turns a flow's receipt into assertions you run with your suite:

```bash
php artisan test
```

A green run means the package is installed and your flow behaves — no `tinker` session, no scratch script.

## Optional: keep every run

To save runs and read their receipts back later, publish and run the migration. It creates one table, `pipe_dream_logs`, with one row per run:

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

Then turn saving on once, in a service provider. [Saving and replaying runs](12-saving-and-replaying.md) covers the details.

**Next:** [Your first flow](03-your-first-flow.md).
