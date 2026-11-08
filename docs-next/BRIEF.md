# Brief for the final docs build

This folder is a proposed rewrite of the Pipe Dream docs. It sits beside `docs/` and doesn't replace it. It's meant as source material for whoever (or whatever) does the final docs build. Read this file first.

Every PHP block and every output block in `docs-next/` is real. `tests/Feature/DocsNextExamplesTest.php` checks that each PHP block is its fixture file verbatim and that each output block is what its script prints. Timings, `duration_ms` and the repo path are normalized. `docs-next/examples/testing/CheckoutFlowTest.php` runs as part of the suite. Run `vendor/bin/phpunit --filter 'DocsNext|CheckoutFlowTest'`.

## What was wrong with the current docs

1. **The first page shows the worst view of the best feature.** The intro and quickstart open on a raw `logs()` dump of anonymous closures. Named stages, named rules, the book-style flow and the receipt don't show up until pages 5 to 8.
2. **Two unrelated examples, and the real one comes last.** Pages 1 to 18 tidy a blog-post title, which is too trivial to show why any of this matters. Page 19 then jumps to a full ad-click flow.
3. **It opens by comparing itself to `Illuminate\Pipeline`.** Most readers don't use it, so it frames Pipe Dream as "Pipeline plus extras" instead of a way to write business logic.
4. **Nine terms arrive before the reader builds anything,** and the Envelope page comes before Rules and Managers.
5. **Learning pages carry reference detail,** such as what hooks may throw, parameter binding and shared-binding clones.
6. **Docs pages hand-build output with loops** (`groupBy->hub()->each(... printf ...)`). There is now `$envelope->receipt()`, so no page should do that.

## Principles (keep these in the final build)

- **One running example: checkout,** from the first page to the last. Click intake appears once, as a second walkthrough, to prove the pattern isn't checkout-specific.
- **Lead with the flow and the receipt.** Any page that can show a book-style flow and the receipt it prints should do so before it explains anything.
- **Flows are formatted like a book:** named hubs, a description on every hub (written as a question where possible), one rule per line, and a blank line between hubs. See `docs/examples/scripts/checkout.php`.
- **No unexplained loops or hand-built output in the docs.** Use `echo $envelope->receipt();`. This is a hard rule from the package author.
- **Every code block is real and tested.** Use the `<!-- example: path -->` and `<!-- output: path -->` markers. Never paste output by hand.
- **Edge cases live in Reference.** Learning pages link to [Outcome tables](18-outcome-tables.md) instead of carrying the detail.
- **Short, direct sentences.** No filler. Tables for lookups.

## Page template

1. Title: a noun ("Rules", "The receipt"), not a question.
2. One sentence on what the page gives you.
3. The code, book style.
4. The receipt or output it prints, as real output.
5. What's going on: a few short paragraphs or bullets.
6. Details, or a link to Reference.
7. `**Next:** [...]`.

## Structure (24 pages become 20)

| Group | Page | Replaces (old `docs/`) |
|---|---|---|
| Start here | 01 Introduction | index |
| | 02 Installation | 01 |
| | 03 Your first flow | 02 (rewritten around checkout) |
| Building blocks | 04 Rules | 06, 07, 21 |
| | 05 Hubs | 03 |
| | 06 Flows | 08 |
| | 07 The receipt | 05 (receipt-first; `steps()`/`logs()` last) |
| | 08 The envelope | 04 |
| Deciding what happens | 09 Pass, flag, skip, stop | 09, 10 |
| | 10 Reacting to failures | 11 |
| | 11 Redirects and views | 12 |
| In production | 12 Saving and replaying runs | 13, 14, 15 |
| | 13 Testing flows | 18 |
| | 14 Events | 16 |
| | 15 Drawing your flow | 17 |
| Walkthroughs | 16 A full checkout | new |
| | 17 A high-volume click intake | 19 |
| Reference | 18 Outcome tables | 20, plus detail moved out of 10 and 11 |
| | 19 API | 22 |
| | 20 Compatibility | 23 |

The nav groups change too: `DocsExamplesTest::GROUPS` must be updated if these pages move into `docs/`.

## Fixtures

- The checkout classes (`Order`, `Warehouse`, 13 rules) and `docs/examples/scripts/checkout*.php` already exist in `docs/examples` from PR #1. `docs-next/examples/scripts/checkout*.php` are symlinks to them.
- `docs-next/examples/` adds:
  - `CheckoutFlow`: the same flow as a class with `build()`, used by the controller, saving, testing and drawing pages.
  - `OneLineFormat`: a custom `ReceiptFormat`.
  - `AskForShippingAddress` and `PlaceOrder`: a redirect rule and a controller.
  - `ChargeSkipped` and `PaymentStepFinished`: events.
  - `testing/CheckoutFlowTest`.
  - A script per page.
- `composer.json` (classmap) and `phpunit.xml` include `docs-next/examples`.
- When merging into `docs/`, move the fixtures into `docs/examples`, change the markers from `docs-next/...` to `docs/...`, and fold `DocsNextExamplesTest` into `DocsExamplesTest`. `checkout.php` and `CheckoutFlow.php` are the same flow written twice (a script for the intro, a class for the app pages). Keep them in sync, or add a test that compares their hubs through introspection.

## Decisions to make in the final build

1. **The click receipt reads worse than the checkout one.** The click fixtures predate the receipt features, so the header says `completed with errors` and the Fulfillment line says `Skipped: the hub's skipIf() returned true`. Giving `IncomingClick` a `ReceiptSummary` (for example "Flagged: Two or more bot signals") and the Fulfillment `skipIf()` a reason would fix it. That is a change to `docs/examples`, so it was left alone here.
2. **`ReceiptHub::wasSkipped()` is only true for `skip()`, not for `skipIf()`.** Readers will reach for `wasSkipped()` and get `false` for a stage the receipt shows as skipped. The docs use `skipReason()` instead. Consider renaming the method, or making it true when every step was skipped.
3. **Origins print absolute paths.** The receipt page shows a repo-relative path and says so. A `basePath` option on the formats would make the real output match.
4. **An unnamed hub's flow is called "Run".** The rule page shows this. It's fine, but it could surprise readers.
5. **Screenshots.** The images in `docs/` (`01-flow.png`, `02-dashboard.png`, `03-click-story.png`) come from the click demo. A checkout screenshot would fit the new running example better.
6. **The website sidebar** still says "Packagist · coming soon". Remove it.
7. **The article** (`pipe-driven-development.md` on kevineaton.dev) uses `->stopOnFatal()`, which doesn't exist. Fatals stop by default.

## Tone, in one example

The introduction's hook, and the voice to keep:

> Pipe Dream turns your business logic into a flow you can read, and every run into a receipt you can read back: every step named, timed and recorded.

Then show the flow, then the receipt, then "When support asks 'why is this order on hold?', this is the answer."
