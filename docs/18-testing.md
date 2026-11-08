<!-- nav-group: Advanced -->
# Testing your flows

How do I test a rule, and a whole flow, in my own application?

A flow is ordinary PHP that returns an envelope, so you test it the way you test anything else: run it, then assert on what the envelope says. Everything below works in PHPUnit, and the same calls work inside a Pest `it()`. These tests extend your application's own `Tests\TestCase`, so Laravel's container is available.

## Test a rule

Run the rule alone in a hub, then assert on your subject, the rule's result and its log lines. A rule's result is stored on the envelope under its class name:

<!-- example: docs/examples/testing/TidyTitleTest.php -->
```php
<?php

namespace Tests\Feature;

use CheckTitleLength;
use CountWords;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeRule;
use Slugger;
use SlugifyTitle;
use TidyTitle;
use Tests\TestCase;

class TidyTitleTest extends TestCase
{
    public function test_it_tidies_the_title_and_says_so(): void
    {
        $post = (object) ['title' => '  hello,   PIPE dream  '];

        $envelope = PipeHub::create()
            ->addPipe(TidyTitle::class)
            ->sendIt($post);

        $this->assertSame('Hello, Pipe Dream', $post->title);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(TidyTitle::class));
        $this->assertFalse($envelope->hasErrors());
        $this->assertSame(
            'Title is now "Hello, Pipe Dream"',
            $envelope->logger()->infos()->firstWhere('key', TidyTitle::class)['data']
        );
    }

    public function test_a_one_word_title_skips_the_length_check(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([CountWords::class, CheckTitleLength::class])
            ->sendIt((object) ['title' => 'Hello']);

        $this->assertSame(PipeRule::SKIP, $envelope->get(CheckTitleLength::class));
    }

    public function test_the_slug_comes_from_whatever_the_container_provides(): void
    {
        $this->app->bind(Slugger::class, fn () => new class extends Slugger {
            public function slug(string $text): string
            {
                return 'a-fixed-slug';
            }
        });

        $post = (object) ['title' => 'Anything at all'];

        PipeHub::create()->addPipe(SlugifyTitle::class)->sendIt($post);

        $this->assertSame('a-fixed-slug', $post->slug);
    }
}
```

The second test shows a rule that skips itself: the result is `PipeRule::SKIP`. The third swaps a dependency. A rule is resolved through the container, so binding a fake before the run is all it takes.

## Test a whole flow

Build the flow in one helper, and use it in every test. Assert on the outcome you care about: the result of a rule, whether a fatal stopped things, which rules were skipped:

<!-- example: docs/examples/testing/PublishFlowTest.php -->
```php
<?php

namespace Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use RejectEmptyTitle;
use SlugifyTitle;
use TidyTitle;
use Tests\TestCase;

class PublishFlowTest extends TestCase
{
    private function publishFlow(object $post): PipeManager
    {
        return PipeManager::create([
            PipeHub::create()
                ->name('Check')
                ->addPipe(RejectEmptyTitle::class),

            PipeHub::create()
                ->name('Publish')
                ->skipIf(fn ($post) => $post->status === 'draft')
                ->addPipes([TidyTitle::class, SlugifyTitle::class]),
        ])
            ->name('Publish a post')
            ->subject($post);
    }

    public function test_a_ready_post_is_tidied_and_slugged(): void
    {
        $post = (object) ['title' => '  hello,   PIPE dream  ', 'status' => 'ready'];

        $envelope = $this->publishFlow($post)->sendIt();

        $this->assertFalse($envelope->hasFatalErrors());
        $this->assertSame('hello-pipe-dream', $post->slug);
        $this->assertCount(3, $envelope->results()->successes());
    }

    public function test_an_empty_title_stops_the_flow_and_nothing_after_it_runs(): void
    {
        $post = (object) ['title' => '', 'status' => 'ready'];

        $envelope = $this->publishFlow($post)->sendIt();

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertStringStartsWith('The post has no title', $envelope->logger()->fatals()->first()['data']);
        $this->assertNull($envelope->get(SlugifyTitle::class));
        $this->assertFalse(isset($post->slug));
    }

    public function test_a_draft_skips_the_publish_hub(): void
    {
        $post = (object) ['title' => 'Work in progress', 'status' => 'draft'];

        $envelope = $this->publishFlow($post)->sendIt();

        $this->assertSame(PipeRule::SKIP, $envelope->get(SlugifyTitle::class));
        $this->assertCount(2, $envelope->results()->skips());
        $this->assertFalse(isset($post->slug));
    }

    public function test_with_throw_fatals_the_stop_is_an_exception(): void
    {
        $this->expectException(PipeRuleFatal::class);

        $this->publishFlow((object) ['title' => '', 'status' => 'ready'])
            ->throwFatals()
            ->sendIt();
    }
}
```

## What to assert on

| To check | Assert on |
|---|---|
| A rule's outcome | `$envelope->get(MyRule::class)` is `PipeRule::SUCCESS`, `FAIL`, `FATAL_FAIL` or `SKIP`. A rule that never ran has no result (`null`). |
| That a flow stopped | `$envelope->hasFatalErrors()`, or `$envelope->logger()->fatals()`. |
| That a failure was recorded | `$envelope->hasErrors()`, or `$envelope->logger()->errors()`. |
| What a rule logged | `$envelope->logger()->infos()->firstWhere('key', MyRule::class)['data']`. |
| Counts of outcomes | `$envelope->results()->successes()`, `failures()`, `fatalFailures()`, `skips()`. |
| A stop as an exception | `->throwFatals()` on the manager, then `expectException(PipeRuleFatal::class)`. |

## Keeping tests free of side effects

A flow writes nothing outside your code unless you give it a strategy to save logs. With no `PipeLoggerPersistStrategy` set or bound, tests like these write nothing, and `->persistLogs(false)` makes that explicit.

Next: [A full click flow](19-click-flow.md).
