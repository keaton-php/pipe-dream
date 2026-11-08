# Pipe Dream

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Tests](https://img.shields.io/github/actions/workflow/status/keaton-php/pipe-dream/tests.yml?branch=1.x&label=tests&style=flat-square)](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/keaton-php/pipe-dream/php?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Total Downloads](https://img.shields.io/packagist/dt/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![License](https://img.shields.io/packagist/l/keaton-php/pipe-dream.svg?style=flat-square)](LICENSE)

Pipe Dream turns a Laravel pipeline into a flow you can read, and a log you can read back: every step named, timed and recorded.

```bash
composer require keaton-php/pipe-dream
```

The service provider is discovered automatically. At the end of this README, a full ad-click flow and the dashboard built from its logs show where this goes.

## What a pipeline is

A pipeline sends one value (the **subject**) through a list of steps (the **pipes**), in order. Each pipe gets the subject, does its bit, and passes it on. Laravel ships one, `Illuminate\Pipeline\Pipeline`, and the pipes can be plain closures:

<!-- example: docs/examples/scripts/pipeline.php -->
```php
<?php

use Illuminate\Pipeline\Pipeline;

$post = (object) ['title' => '  hello,   PIPE dream  '];

app(Pipeline::class)
    ->send($post)
    ->through([
        function ($post, $next) {
            $post->title = trim($post->title);

            return $next($post);
        },
        function ($post, $next) {
            $post->title = preg_replace('/\s+/', ' ', $post->title);

            return $next($post);
        },
        function ($post, $next) {
            $post->title = ucwords(strtolower($post->title));

            return $next($post);
        },
    ])
    ->thenReturn();

echo $post->title;
```

<!-- output: docs/examples/scripts/pipeline.php -->
```text
Hello, Pipe Dream
```

## The same thing as a PipeHub

A `PipeHub` is Pipe Dream's pipeline. The same three steps:

<!-- example: docs/examples/scripts/hub.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
        fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

echo $post->title;
```

<!-- output: docs/examples/scripts/hub.php -->
```text
Hello, Pipe Dream
```

No `$next` to call and nothing to return: the hub moves on to the next pipe by itself.

Same three steps, same result. So far the plain pipeline has done everything the hub did.

## Now ask it what happened

Here is the difference. A plain pipeline hands you the final value and nothing else: if the title came out wrong, you are left guessing which step broke it. The hub kept notes. Ask it what happened:

<!-- example: docs/examples/scripts/hub-log.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
        fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

foreach ($envelope->logs() as $record) {
    printf("%-11s %-24s %s\n", $record['type'], $record['key'], $record['data']);
}
```

<!-- output: docs/examples/scripts/hub-log.php -->
```text
info        PipeHub:sendIt           Sending subject through all pipes
timerStart  Closure@hub-log.php:9    Executing rule Closure@hub-log.php:9
returnValue Closure@hub-log.php:9    hello,   PIPE dream
timerEnd    Closure@hub-log.php:9    Finished executing rule Closure@hub-log.php:9
timerStart  Closure@hub-log.php:10   Executing rule Closure@hub-log.php:10
returnValue Closure@hub-log.php:10   hello, PIPE dream
timerEnd    Closure@hub-log.php:10   Finished executing rule Closure@hub-log.php:10
timerStart  Closure@hub-log.php:11   Executing rule Closure@hub-log.php:11
returnValue Closure@hub-log.php:11   Hello, Pipe Dream
timerEnd    Closure@hub-log.php:11   Finished executing rule Closure@hub-log.php:11
info        PipeHub:sendIt           Finalized result!
```

Every pipe was timed, and what each one returned was recorded. You can see exactly where `hello,   PIPE dream` became `Hello, Pipe Dream`, without adding a single line of logging to your code. `$envelope->results()` holds the same results, ready to check in code.

Now break it on purpose. Change the second step so it eats every space, and run the hub again:

<!-- example: docs/examples/scripts/hub-log-broken.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
        fn ($post) => $post->title = preg_replace('/\s+/', '', $post->title), // oops: '' instead of ' '
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

foreach ($envelope->logs() as $record) {
    if ($record['type'] === 'returnValue') {
        printf("%-30s %s\n", $record['key'], $record['data']);
    }
}
```

<!-- output: docs/examples/scripts/hub-log-broken.php -->
```text
Closure@hub-log-broken.php:9   hello,   PIPE dream
Closure@hub-log-broken.php:10  hello,PIPEdream
Closure@hub-log-broken.php:11  Hello,pipedream
```

You don't have to guess which step ate the spaces. The log already points at the second one: it's the first line where they're gone.

That's the whole difference. A pipeline tells you the answer. A hub hands you the receipt.

And that's only the start. Once every step is a named, recorded thing, a lot more opens up:

- **Rules as classes:** each pipe becomes a small, testable class with a name, so the log reads like a description of your business.
- **Stacked hubs:** group steps into stages and run the stages as one flow, with one log.
- **Control flow:** skip a step, stop a flow early, redirect, or catch a failure without losing the log.
- **Saved runs:** store every log and query it later: which runs hit problems, and exactly where.
- **A flow you can draw:** read a flow back and render it as a page, then watch real traffic move through it.

All of it builds on what you just saw. The docs pick it up from here.

## Guess what else you can do?

A few more things the hub gives you for free.

### The subject travels in an envelope

The hub wraps your subject in a `PipeEnvelope`, and that is what each pipe receives as its first parameter (and what `sendIt()` returns). Leave that parameter untyped, or type it `PipeEnvelope`. Reads, writes and method calls go straight through to your object:

<!-- example: docs/examples/scripts/envelope.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
    ])
    ->sendIt($post);

echo get_class($envelope), PHP_EOL;
echo $envelope->title, PHP_EOL;                         // reads go straight to your object
echo $envelope->subject() === $post ? 'the same $post' : 'a copy', PHP_EOL;
```

<!-- output: docs/examples/scripts/envelope.php -->
```text
Keaton\PipeDream\PipeEnvelope
hello,   PIPE dream
the same $post
```

One limit: a property read through the envelope is a copy, so `$envelope->tags[] = 'new'` changes nothing on your object. Read the array, change it, and assign the whole property back: `$envelope->tags = [...$envelope->tags, 'new'];`.

### Pipes can share values

The envelope also holds values of its own, for the pipes to pass along: `set()` one in a pipe, `get()` it in a later one. That makes small state machines easy:

<!-- example: docs/examples/scripts/state.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => 'draft: Hello, Pipe Dream'];

$envelope = PipeHub::create()
    ->addPipes([
        function ($post) {
            $post->set('state', str_starts_with($post->title, 'draft:') ? 'draft' : 'ready');
        },
        function ($post) {
            if ($post->get('state') === 'draft') {
                $post->title = trim(substr($post->title, 6));
                $post->set('state', 'in review');
            }
        },
    ])
    ->sendIt($post);

echo $envelope->get('state'), ': ', $post->title;
```

<!-- output: docs/examples/scripts/state.php -->
```text
in review: Hello, Pipe Dream
```

## Stack hubs into a PipeManager

A `PipeManager` runs several hubs in order, on the same envelope: one run, one log.

<!-- example: docs/examples/scripts/stack.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->addPipes([
            fn ($post) => $post->title = ucwords(strtolower(trim($post->title))),
            fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        ]),

    PipeHub::create()
        ->name('Slug')
        ->addPipes([
            fn ($post) => $post->slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($post->title)), '-'),
        ]),
])
    ->subject($post)
    ->sendIt();

echo $post->slug, PHP_EOL;
echo $envelope->results()->count(), ' results in one log of ', count($envelope->logs()), ' records', PHP_EOL;
```

<!-- output: docs/examples/scripts/stack.php -->
```text
hello-pipe-dream
3 results in one log of 15 records
```

## Rules: pipes as classes

A closure is fine for a small step. A `PipeRule` class gives a step a name, a home and a test. The hub runs the public method named after the class. Inside it, `$this->info('…')` writes a line to the log against the rule, and returning `self::SUCCESS` records that the step passed (the other results, and `$this->error()` / `$this->fatal()`, come in *Controlling the flow* below):

<!-- example: docs/examples/TidyTitle.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class TidyTitle extends PipeRule
{
    public static $name = 'Tidy the title';
    public static $description = 'Trim it, collapse the spaces, title-case it';

    public function tidyTitle($post)
    {
        $post->title = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($post->title))));

        $this->info("Title is now \"{$post->title}\"");

        return self::SUCCESS;
    }
}
```

The method is called through Laravel's container, so **dependency injection just works**: ask for a class and you get it. Here the rule asks for `Slugger`, a plain class of yours:

<!-- example: docs/examples/Slugger.php -->
```php
<?php

class Slugger
{
    public function slug(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }
}
```

And the rule that uses it:

<!-- example: docs/examples/SlugifyTitle.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class SlugifyTitle extends PipeRule
{
    public static $name = 'Slugify the title';
    public static $description = 'Make the URL slug';

    public function slugifyTitle($post, Slugger $slugger)
    {
        $post->slug = $slugger->slug($post->title);

        return self::SUCCESS;
    }
}
```

The first parameter receives **the envelope**, whatever you call it (leave it untyped, or type it `PipeEnvelope`; a different class type there is resolved from the container instead). Inside a rule, `$this->words = 3` and `$this->words` set and read a shared value on it (`$this->setWordsResult(3)` and `$this->getWordsResult()` do the same):

<!-- example: docs/examples/CountWords.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class CountWords extends PipeRule
{
    public static $name = 'Count the words';
    public static $description = 'Remember how long the title is';

    public function countWords($post)
    {
        $this->words = str_word_count($post->title);

        return self::SUCCESS;
    }
}
```

A rule can also decide that it has nothing to do and **skip** itself. For a one-word title, this one records `PipeRule::SKIP` (4) and logs its reason. It also uses `$this->error()`, which records a failure (`PipeRule::FAIL`) with its message and, by default, lets the hub carry on to the next rule:

<!-- example: docs/examples/CheckTitleLength.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class CheckTitleLength extends PipeRule
{
    public static $name = 'Check the title length';
    public static $description = 'Long titles get cut off in feeds';

    public function checkTitleLength($post)
    {
        if ($this->words < 2) {
            return $this->skip('A one-word title is fine');
        }

        if ($this->words > 12) {
            $this->error("The title has {$this->words} words");
        }

        return self::SUCCESS;
    }
}
```

Use them like closures, by class name:

<!-- example: docs/examples/scripts/rules.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        TidyTitle::class,
        CountWords::class,
        CheckTitleLength::class,
        SlugifyTitle::class,
    ])
    ->sendIt($post);

echo $post->slug, ' (', $envelope->get('words'), ' words)', PHP_EOL;

foreach ($envelope->results() as $result) {
    echo $result->rule(), ': ', $result->result(), PHP_EOL;
}
```

<!-- output: docs/examples/scripts/rules.php -->
```text
hello-pipe-dream (3 words)
TidyTitle: 1
CountWords: 1
CheckTitleLength: 1
SlugifyTitle: 1
```

`1` is `PipeRule::SUCCESS`.

## All together

Name each hub, describe it, fill it with rules, stack the hubs in a manager, and send the post through. Closures and rules mix freely. `skipIf()` skips a hub's rules when it doesn't apply (each is logged as skipped), and `then()` runs after a hub's last rule:

<!-- example: docs/examples/scripts/together.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => '  hello,   PIPE dream  ', 'status' => 'ready'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->description('Make the title presentable')
        ->addPipes([TidyTitle::class]),

    PipeHub::create()
        ->name('Check')
        ->description('Is the title any good?')
        ->addPipes([CountWords::class, CheckTitleLength::class])
        ->then(fn ($post) => $post->readingTime = ceil($post->get('words') / 200).' min'),

    PipeHub::create()
        ->name('Publish')
        ->description('Give it a slug and publish it, unless it is a draft')
        ->skipIf(fn ($post) => $post->status === 'draft')
        ->addPipes([
            SlugifyTitle::class,
            fn ($post) => $post->status = 'published',
        ]),
])
    ->name('Publish a post')
    ->subject($post)
    ->sendIt();

foreach ($envelope->logs() as $record) {
    if ($record['data'] === 'Sending subject through all pipes') {
        echo $record['context']['name'], PHP_EOL;
    } elseif ($record['type'] === 'returnValue') {
        echo '  ', $record['key'], ': ', $record['data'], PHP_EOL;
    }
}

echo $post->status, ', ', $post->readingTime, ' read', PHP_EOL;
```

<!-- output: docs/examples/scripts/together.php -->
```text
Tidy
  TidyTitle: 1
Check
  CountWords: 1
  CheckTitleLength: 1
Publish
  SlugifyTitle: 1
  Closure@together.php:26: published
published, 1 min read
```

The flow reads like a table of contents, and the log tells the same story. With `'status' => 'draft'`, the Publish hub's pipes are logged as skipped instead, and the post stays a draft.

## Controlling the flow

When a step goes wrong, two things decide what happens next: what the rule says happened, and how far its hub lets that reach. Ask what you want to happen, and the answer is one of three:

| You want to | In the rule | On the hub |
|---|---|---|
| note it and keep going: the next pipe runs | `$this->error('why')` | nothing to set |
| end this hub and move on to the next one | `$this->fatal('why')` | `->fatalsStopThisHubOnly()` |
| stop everything | `$this->fatal('why')` | nothing to set: this is the default |

- `error()` records a failure (`PipeRule::FAIL`) with its message, and the next pipe runs. An unexpected exception in a rule (any `Throwable`, PHP errors such as `TypeError` included) is handled the same way.
- `fatal()` records a fatal (`PipeRule::FATAL_FAIL`), and by default nothing after it runs: not the rest of its hub, not any later hub, and neither the hub's `then()` nor the manager's `then()`.
- `fatalsStopThisHubOnly()` contains a hub's fatals: the rest of that hub and its `then()` do not run, the manager moves on to the next hub, and the manager's `then()` still runs. It only matters inside a manager; a hub run on its own is the whole flow.
- `errorsAreFatal()` on a hub makes every `error()` and every unexpected exception in its pipes a fatal, and then `fatalsStopThisHubOnly()` decides how far it reaches. A rule can do the same for itself with `protected $errorsAreFatal = true;`.

`RejectEmptyTitle` stops on an empty title, and `CheckTitleLength` (from *Rules*) calls `error()` on a title over twelve words. Here they are in a three-hub flow, run five ways. Each line lists the rules that ran, in order, with what they recorded; a rule the stop reached never runs, so it has no result at all:

<!-- example: docs/examples/RejectEmptyTitle.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class RejectEmptyTitle extends PipeRule
{
    public static $name = 'Reject empty titles';
    public static $description = 'A post needs a title';

    public function rejectEmptyTitle($post)
    {
        if (trim($post->title) === '') {
            $this->fatal('The post has no title');
        }

        return self::SUCCESS;
    }
}
```

<!-- example: docs/examples/scripts/reach.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;

$publish = function (string $title, ?Closure $check = null, ?Closure $edit = null) {
    $check ??= fn (PipeHub $hub) => $hub;
    $edit ??= fn (PipeHub $hub) => $hub;
    $managerThen = 'did not run';

    $envelope = PipeManager::create([
        $check(PipeHub::create()->name('Check')->addPipe(RejectEmptyTitle::class)),
        $edit(PipeHub::create()->name('Edit')->addPipes([TidyTitle::class, CountWords::class, CheckTitleLength::class])),
        PipeHub::create()->name('Publish')->addPipe(SlugifyTitle::class),
    ])
        ->subject((object) ['title' => $title])
        ->then(function () use (&$managerThen) {
            $managerThen = 'ran';
        })
        ->sendIt();

    $outcome = [PipeRule::SUCCESS => 'ok', PipeRule::FAIL => 'error', PipeRule::FATAL_FAIL => 'FATAL', PipeRule::SKIP => 'skip'];
    $ran = $envelope->results()->map(fn ($result) => $result->rule().' '.$outcome[$result->result()])->implode(', ');

    return "{$ran}. Manager then(): {$managerThen}";
};

$long = 'A title that keeps on going for many more words than any feed will show';

echo "1. error() in Edit:\n   ", $publish($long), PHP_EOL;
echo "2. fatal() in Check:\n   ", $publish(''), PHP_EOL;
echo "3. fatal() in Check, with fatalsStopThisHubOnly():\n   ", $publish('', fn ($hub) => $hub->fatalsStopThisHubOnly()), PHP_EOL;
echo "4. error() in Edit, with errorsAreFatal():\n   ", $publish($long, null, fn ($hub) => $hub->errorsAreFatal()), PHP_EOL;
echo "5. error() in Edit, with errorsAreFatal() and fatalsStopThisHubOnly():\n   ", $publish($long, null, fn ($hub) => $hub->errorsAreFatal()->fatalsStopThisHubOnly()), PHP_EOL;
```

<!-- output: docs/examples/scripts/reach.php -->
```text
1. error() in Edit:
   RejectEmptyTitle ok, TidyTitle ok, CountWords ok, CheckTitleLength error, SlugifyTitle ok. Manager then(): ran
2. fatal() in Check:
   RejectEmptyTitle FATAL. Manager then(): did not run
3. fatal() in Check, with fatalsStopThisHubOnly():
   RejectEmptyTitle FATAL, TidyTitle ok, CountWords ok, CheckTitleLength skip, SlugifyTitle ok. Manager then(): ran
4. error() in Edit, with errorsAreFatal():
   RejectEmptyTitle ok, TidyTitle ok, CountWords ok, CheckTitleLength FATAL. Manager then(): did not run
5. error() in Edit, with errorsAreFatal() and fatalsStopThisHubOnly():
   RejectEmptyTitle ok, TidyTitle ok, CountWords ok, CheckTitleLength FATAL, SlugifyTitle ok. Manager then(): ran
```

### onError() and onFatal()

Hubs and managers both take two hooks, each called with the envelope and the exception:

- `onError(fn ($subject, $exception) => …)` runs for every `error()` and unexpected exception a pipe records, and then the next pipe runs. A hub's hook hears about its own pipes; a manager's hears about every hub's, after the hub's own hook.
- `onFatal(fn ($subject, $exception) => …)` runs when a fatal stops things, with the `PipeRuleFatal`. A hub's hook runs when a fatal stops that hub, whether it is contained or not; a manager's runs when a fatal stops the whole flow (not for one contained to its hub), after the hub's. Both run before `sendIt()` returns or throws, so what they log is saved with the rest. An error that `errorsAreFatal()` turns into a fatal goes to `onFatal()`, not `onError()`, and the fatal's `getPrevious()` is the original error.

What a hook throws decides what happens next. A redirect or view is handed off and leaves `sendIt()` (see *Redirects and views* below), and a `PipeHubException` leaves `sendIt()` as a configuration error, from either hook; in both cases that exception, not a fatal, is what reaches your code. A `PipeRuleFatal` thrown by `onError()` escalates the error: it is recorded and stops things like any fatal. Any other exception from `onError()` means the hub cannot carry on, so it stops as for a fatal wrapping that exception. Any other exception from `onFatal()` is recorded as a fatal and the stop carries on exactly as it would have.

`then()` callbacks are handled like pipes: an exception thrown in one is an error (recorded, sent to `onError()`, and the flow carries on), or a fatal under the hub's `errorsAreFatal()`, and a `PipeRuleFatal` thrown there stops things like any fatal. If a `skipIf()` callback or an event listener throws, the hub cannot carry on either, so it stops as for a fatal, with the exception as the fatal's `getPrevious()`.

### Redirects and views

A rule can also hand control back to your controller: throwing `PipeRuleRedirect` (`PipeRuleRedirect::toUrl($url)` or `::toRoute($name)`) or `PipeRuleLoadView` (`PipeRuleLoadView::load($view, $data)`) ends the flow and leaves `sendIt()` as that exception. One thrown from `then()`, `onError()` or `onFatal()` does the same.

<!-- example: docs/examples/SendDraftsToPreview.php -->
```php
<?php

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeRule;

class SendDraftsToPreview extends PipeRule
{
    public static $name = 'Send drafts to the preview';
    public static $description = 'Drafts are previewed, not published';

    public function sendDraftsToPreview($post)
    {
        if ($post->status === 'draft') {
            PipeRuleRedirect::toUrl("/preview/{$post->id}");
        }

        return self::SUCCESS;
    }
}
```

### Quiet or thrown

By default a stop is quiet: `sendIt()` returns the envelope, and `hasFatalErrors()` tells you a fatal stopped it. So a controller wraps the run in a `try`/`catch` for the redirects and views, and checks the envelope for a fatal:

<!-- example: docs/examples/PublishPost.php -->
```php
<?php

use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class PublishPost
{
    public function __invoke(object $post)
    {
        try {
            $envelope = PipeManager::create([
                PipeHub::create()
                    ->name('Check')
                    ->addPipes([RejectEmptyTitle::class, SendDraftsToPreview::class]),

                PipeHub::create()
                    ->name('Publish')
                    ->addPipes([TidyTitle::class, SlugifyTitle::class]),
            ])
                ->name('Publish a post')
                ->subject($post)
                ->sendIt();
        } catch (PipeRuleRedirect $e) {
            return redirect($e->redirectTo);
        } catch (PipeRuleLoadView $e) {
            return view($e->view, $e->data);
        }

        // By default a fatal is not thrown: it is recorded on the envelope.
        if ($envelope->hasFatalErrors()) {
            return response()->json(['error' => 'This post cannot be published'], 422);
        }

        return redirect("/posts/{$post->slug}");
    }
}
```

<!-- example: docs/examples/scripts/controller.php -->
```php
<?php

$publish = app(PublishPost::class);

$posts = [
    (object) ['id' => 1, 'title' => '  hello,   PIPE dream  ', 'status' => 'ready'],
    (object) ['id' => 2, 'title' => '   ', 'status' => 'ready'],
    (object) ['id' => 3, 'title' => 'Work in progress', 'status' => 'draft'],
];

foreach ($posts as $post) {
    $response = $publish($post);

    echo $post->id, ': ', $response->getStatusCode(), ' ', $response->headers->get('Location') ?? $response->getContent(), PHP_EOL;
}
```

<!-- output: docs/examples/scripts/controller.php -->
```text
1: 302 http://localhost/posts/hello-pipe-dream
2: 422 {"error":"This post cannot be published"}
3: 302 http://localhost/preview/3
```

To get the fatal as an exception instead, call `throwFatals()` on the manager (or on a hub you run on its own). When a fatal stops the flow, `sendIt()` throws that same `PipeRuleFatal` (unless an `onFatal()` hook hands off a redirect or view, or throws a configuration error, which leaves instead), but only after everything that happens without it: the fatal is logged, `onFatal()` runs, `then()` does not run and the log is persisted just as it would be without it (see *Keeping the log* below: with `persistLogs(false)`, or no strategy set, nothing is saved, and an exception from saving takes the fatal's place). `$e->subject()` is the envelope. A fatal contained by `fatalsStopThisHubOnly()` is never thrown, because the flow carried on:

<!-- example: docs/examples/scripts/throw-fatals.php -->
```php
<?php

use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$flow = fn (object $post, bool $checkStopsOnlyItself = false) => PipeManager::create([
    PipeHub::create()
        ->name('Check')
        ->addPipe(RejectEmptyTitle::class)
        ->fatalsStopThisHubOnly($checkStopsOnlyItself),

    PipeHub::create()
        ->name('Publish')
        ->addPipes([TidyTitle::class, SlugifyTitle::class]),
])
    ->name('Publish a post')
    ->subject($post)
    ->onFatal(function ($post, $fatal) {
        echo '   onFatal(): ', $fatal->getMessage(), PHP_EOL;
    });

echo 'Quiet (the default):', PHP_EOL;
$envelope = $flow((object) ['title' => ''])->sendIt();
echo '   returned, hasFatalErrors() is ', var_export($envelope->hasFatalErrors(), true), PHP_EOL;

echo 'With throwFatals():', PHP_EOL;
try {
    $flow((object) ['title' => ''])->throwFatals()->sendIt();
} catch (PipeRuleFatal $e) {
    // The exception carries the envelope, its log included.
    echo '   caught "', $e->getMessage(), '", hasFatalErrors() is ', var_export($e->subject()->hasFatalErrors(), true), PHP_EOL;
}

echo 'With throwFatals(), the fatal contained to Check:', PHP_EOL;
$post = (object) ['title' => ''];
$envelope = $flow($post, true)->throwFatals()->sendIt();
echo '   returned, Publish ran: ', var_export(isset($post->slug), true), PHP_EOL;
```

<!-- output: docs/examples/scripts/throw-fatals.php -->
```text
Quiet (the default):
   onFatal(): The post has no title
   returned, hasFatalErrors() is true
With throwFatals():
   onFatal(): The post has no title
   caught "The post has no title", hasFatalErrors() is true
With throwFatals(), the fatal contained to Check:
   returned, Publish ran: true
```

The last run prints no `onFatal()` line: the manager's hook is for a fatal that stops the flow, and this one was contained to its hub. Inside a manager, a hub's own `throwFatals()` is ignored: set it on the manager. Redirects, views and configuration errors leave `sendIt()` the same way with or without it.

## Keeping the log

Publish and run the migration:

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

Then give the envelope a `DatabasePersistStrategy`. Wrap the subject yourself with `PipeEnvelope::wrap()` to set things like this before the run:

<!-- example: docs/examples/scripts/database.php -->
```php
<?php

use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeEnvelope::wrap($post)->persistWith(new DatabasePersistStrategy());

PipeManager::create([
    PipeHub::create()->name('Tidy')->addPipes([TidyTitle::class]),
    PipeHub::create()->name('Slug')->addPipes([SlugifyTitle::class]),
])->name('Publish a post')->subject($envelope)->sendIt();

PipeDreamLog::where('run_id', $envelope->runId())->count();   // 1
```

A manager saves once, when the flow ends, however it ended: one row per run, in `pipe_dream_logs`. Saving the same run again updates its row. Read a run back later, with every record intact:

<!-- example: docs/examples/scripts/rebuild.php -->
```php
<?php

use Keaton\PipeDream\Models\PipeDreamLog;

$run = PipeDreamLog::where('flow', 'Publish a post')->latest()->firstOrFail();

$run->has_fatals;                                                       // false
$run->logger()->infos()->firstWhere('key', TidyTitle::class)['data'];    // 'Title is now "Hello, Pipe Dream"'
```

## The payoff: a full ad-click flow

This is where it goes: one controller, eight hubs, one log per click. The hubs are the stages of a real click flow: validation and a session, tracking, geo exclusions, the advertiser's suppression list (skipped for newsletter-type campaigns), blacklists and graylists, bot checks (skipped when a campaign turns them off), caps and budget, and routing.

The subject is `IncomingClick`, a class of your own built from the request. It has fields (`id`, `campaign`, `country`, `ip`, `userAgent`, `publisher`, `email`) and a few methods the flow calls through the envelope: `campaignSettings()` (the campaign's countries, type and bot-check switch), `flag($reason)` / `isFlagged()`, `isListed()`, `isTest()`, `markComplete()` and `destination()`.

<!-- example: docs/examples/HandleAdClick.php -->
```php
<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

class HandleAdClick
{
    public function __invoke(Request $request)
    {
        $click = PipeEnvelope::wrap(new IncomingClick($request))
            ->persistWith(new DatabasePersistStrategy());

        $started = microtime(true);

        try {
            PipeManager::create([

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
                    ->skipIf(fn ($click) => $click->isFlagged() || $click->isTest())
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
                ->subject($click)
                ->persistLogs(false)             // we persist once, below, after the timer
                ->then(fn ($click) => $click->markComplete())
                ->sendIt();

        } catch (PipeRuleRedirect $e) {
            return redirect()->to($e->redirectTo);
        } catch (PipeRuleLoadView $e) {
            return view($e->view, $e->data);
        } finally {
            $click->logger()->info('Intake', sprintf('Handled in %.1f ms', (microtime(true) - $started) * 1000));
            $click->persist();                   // one row per click, whatever happened
        }

        return redirect()->to($click->destination());
    }
}
```

A fatal in any hub ends the click's flow, except in *Fulfillment & caps*: there `errorsAreFatal()` turns a failed charge into a fatal, and `fatalsStopThisHubOnly()` contains it to that hub, so the click is still routed.

The controller saves the log once, in `finally`, after recording how long the click took (`->persistLogs(false)` stops the manager saving it first), so every click gets exactly one row, including the ones that end early or hand off to a view.

All of these rules are in `docs/examples/`. Some have real bodies (`CheckAdvertiserSuppressionList`, `ChallengeGraylistedPublishers`, `DetectHostingProviderIp`, `MatchKnownBotUserAgents`, `RecordBudgetSpend`, and the small ones the tests rely on: `RequireTrackingParameters`, `RecognizeCampaign`, `StartClickSession`, `ResolveVisitorCountry`, `EnforceCampaignCountries`, `MatchIpBlacklist`, `ChooseDestination`); the rest are placeholders that only return `SUCCESS`, named for what your own rule would do (`VerifyClickSignature`, `SetAttributionCookie`, `RecordConversionPath`, `EnforceAdvertiserGeoExclusions`, `MatchPublisherBlacklist`, `ScoreClickVelocity`, `EnforceDailyClickCap`).

### See it running

A demo app pushes 500 synthetic ad clicks through a five-stage, 14-rule flow like this one. Every page is built from the flow definition and the persisted logs, with nothing written by hand. The flow page is rendered from the code itself:

![The flow, rendered from its code](docs/01-flow.png)

| Dashboard built from the logs | One click's full story | The whole flow is one readable file |
|---|---|---|
| ![Dashboard](docs/02-dashboard.png) | ![One click's story](docs/03-click-story.png) | ![Source](docs/04-code.png) |

## Reference

### What a rule can return

| In the rule | Recorded | What happens next |
|---|---|---|
| `return self::SUCCESS;` (or return nothing) | `SUCCESS` (1) | the next pipe runs |
| `return self::FAIL;` | `FAIL` (2) | the next pipe runs; no error record is added |
| `return self::FATAL_FAIL;` | `FATAL_FAIL` (3) | the next pipe runs: only the value is recorded, with no fatal record, so `hasFatalErrors()` stays false. To stop, call `fatal()` |
| `return $this->skip('why');` | `SKIP` (4), and the reason | the next pipe runs |
| `$this->error('why');` | `FAIL` (2), and an error record | `onError()` runs, then the next pipe runs (unless errors are fatal, see below) |
| `$this->fatal('why');` | `FATAL_FAIL` (3), and a fatal record | nothing after it runs: by default the rest of the flow, with `fatalsStopThisHubOnly()` the rest of this hub |
| any other exception or PHP error, except a `PipeRuleFatal` (handled as `fatal()`) and the three below | `FAIL` (2), and an error record | as `error()` |

A closure pipe's result is whatever it returns (nothing, or the subject itself, counts as `SUCCESS`). `error()` and `fatal()` records point at the line in your rule that called them. Three exceptions are not failures and are never recorded as `FAIL`: `PipeRuleRedirect` and `PipeRuleLoadView` hand control back to your controller, and `PipeHubException`, a configuration error, is thrown straight out of the flow.

### Stopping, in one table

How far a stop reaches:

| On the hub | A rule calls `fatal()` | A rule calls `error()`, or throws an unexpected exception |
|---|---|---|
| (default) | **the whole flow stops**: the rest of this hub, every later hub, this hub's `then()` and the manager's `then()` do not run; the hub's `onFatal()` runs, then the manager's | recorded as `FAIL`; the hub's `onError()` runs, then the manager's; the next pipe runs (unless an `onError()` hook throws, see *onError() and onFatal()*) |
| `->fatalsStopThisHubOnly()` | **this hub stops**: the rest of it and its `then()` do not run, and its `onFatal()` runs (not the manager's); the next hub runs, and the manager's `then()` still runs | as default |
| `->errorsAreFatal()` | as default | handled as a `fatal()` (recorded as `FATAL_FAIL`, sent to `onFatal()` instead of `onError()`), so **the whole flow stops** |
| both | as `fatalsStopThisHubOnly()` | handled as a `fatal()`, so **this hub stops** |

How the stop reaches the caller, set on the manager (or on a hub run on its own; inside a manager a hub's own setting is ignored):

| | Default | `->throwFatals()` |
|---|---|---|
| a fatal stops the flow | `sendIt()` returns the envelope; `hasFatalErrors()` is true | `sendIt()` throws the same `PipeRuleFatal`, after it is logged, `onFatal()` runs, `then()` does not run and the log is persisted as configured (a redirect, view or configuration error thrown by `onFatal()` leaves instead) |
| a fatal is contained to its hub | the flow carries on | the flow carries on, and nothing is thrown |
| a redirect, view or configuration error | thrown from `sendIt()` | thrown from `sendIt()` |

"Exception" means any `Throwable`, PHP errors such as `TypeError` included. A rule with `protected $errorsAreFatal = true;` handles its own errors and exceptions as fatals, as if its hub used `errorsAreFatal()`. Redirects, views and configuration errors (`PipeHubException`) are never handled as errors or fatals: wherever they are thrown, they leave `sendIt()` as themselves (a hub or manager may write a configuration error to the log first). A `then()` callback, the hub's or the manager's, is handled like a pipe: an exception thrown in it is an error (recorded, sent to `onError()`, and the flow carries on), or a fatal under the hub's `errorsAreFatal()`; a `PipeRuleFatal` thrown in it is a fatal. A `skipIf()` callback or an event listener that throws stops the hub as a fatal, which reaches as far as any other. What a hook throws: a redirect, view or `PipeHubException` leaves `sendIt()` as itself; a `PipeRuleFatal` from `onError()` is a fatal; any other exception from `onError()` stops the hub as a fatal wrapping it; any other exception from `onFatal()` is recorded as a fatal and the stop carries on as it would have. A callback parameter named `$subject` gets the envelope, and a hook parameter named `$exception` gets the exception, by name; otherwise the envelope goes to the first parameter and, for hooks, the exception to the second, whatever they are called. Either way that happens only when the parameter's declared type accepts the value (an untyped parameter always does); a hook parameter typed as an exception class, whether it is named `$exception` or is the second parameter, always gets the real exception, so a mismatch is a PHP `TypeError`, never a different exception built for it. The fatal thrown by `throwFatals()` is the same `PipeRuleFatal` instance that was raised, so `getPrevious()` is the error or exception behind a fatal from `errorsAreFatal()`, and its `subject()` is set to the envelope before it is thrown. Everything above that "leaves `sendIt()`" (a fatal, a redirect, a view or a configuration error) assumes saving the log succeeds: the log is saved as the run ends, and an exception from the persistence strategy takes the place of whatever was leaving.

### Skipping, in one table

| | What runs | What is logged | `then()` |
|---|---|---|---|
| `->skipIf(fn ($s) => …)` returns true | no rule executes | every rule, as `SKIP` with the reason (plus a `PipeHub:event` line when a skip event is set) | runs |
| `->skip()` | nothing in the hub | one "Skipping hub" line (plus a `PipeHub:event` line when a skip event is set) | does not run |
| `$envelope->skipAll()` (from a rule or callback) | nothing after that point, in this hub or any later hub | every remaining rule, as `SKIP` | runs |
| a rule returns `$this->skip('…')` | the rule ran and decided to skip | that rule, as `SKIP` with the reason | runs |

### Rule options

- `public static $runFunction = 'check';` runs a method with another name.
- `public static $saveResultAs = 'title_verdict';` also stores the rule's result under that key.
- `protected $errorsAreFatal = true;` turns the rule's `error()` calls and unexpected exceptions into fatals.
- `$this->foo = $value` / `$this->foo`, and `$this->setFooResult($value)` / `$this->getFooResult()`, write and read `foo` on the envelope while the rule runs.
- Rule results are stored on the envelope under the class name: `$envelope->get(TidyTitle::class)`.
- `addPipe()` and `addPipes()` throw a `PipeHubException` for a class that does not exist or is not a `PipeRule`, so a typo never quietly drops a step.

### Reading the log

Every record is an array with a `time`, a `key` (usually the rule class, a closure's file and line, or a `PipeHub:…` / `PipeManager:…` name), a `type` (`info`, `warning`, `error`, `fatal`, `timerStart`, `timerEnd`, `returnValue`), its `data` and a `context` array (a hub's start and skip records carry its name and description). The logger has slices for the common questions: `errors()`, `fatals()`, `warnings()`, `timers()`, `returnValues()` and `recordsByKey($key)`; `$envelope->hasErrors()` and `$envelope->hasFatalErrors()` answer the most common ones, and `$envelope->results()` has `successes()`, `failures()`, `fatalFailures()` and `skips()`. Add your own lines with `$envelope->logger()->info($key, $message)`. Every envelope has a `runId()`, a uuid that identifies the run.

### Log levels

`$envelope->logLevel(PipeLogger::LEVEL_EVERYTHING)` is the default; `LEVEL_NO_TIMERS` drops the rule timers; `LEVEL_PROBLEMS` keeps only warnings, errors and fatals. Records below the level are never written. Results are recorded whatever the level.

### Events

A hub can dispatch Laravel events at five points. Hub events are constructed with the envelope; per-rule events with the envelope and the rule's key. Declare the event classes:

<!-- example: docs/examples/BotCheckEvents.php -->
```php
<?php

use Keaton\PipeDream\PipeEnvelope;

class BotChecksStarted
{
    public function __construct(public PipeEnvelope $click) {}
}

class BotChecksFinished
{
    public function __construct(public PipeEnvelope $click) {}
}

class BotChecksSkipped
{
    public function __construct(public PipeEnvelope $click) {}
}

class RuleStarting
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}

class RuleFinished
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}
```

Then name them on the hub:

<!-- example: docs/examples/scripts/events.php -->
```php
<?php

use Illuminate\Support\Facades\Event;
use Keaton\PipeDream\PipeHub;

$fired = [];

Event::listen(
    [BotChecksStarted::class, BotChecksFinished::class, BotChecksSkipped::class, RuleStarting::class, RuleFinished::class],
    function ($event) use (&$fired) {
        $fired[] = class_basename($event).(isset($event->rule) ? '('.class_basename($event->rule).')' : '');
    }
);

PipeHub::create()
    ->name('Bot checks')
    ->addPipes([DetectHostingProviderIp::class, MatchKnownBotUserAgents::class])
    ->broadcastEventBefore(BotChecksStarted::class)          // before the first rule
    ->broadcastEventAfter(BotChecksFinished::class)          // after the last rule, before then()
    ->broadcastEventOnSkip(BotChecksSkipped::class)          // instead of both, when the hub is skipped
    ->broadcastEventBeforeEachPipe(RuleStarting::class)      // just before each rule runs
    ->broadcastEventAfterEachPipe(RuleFinished::class)       // as each rule hands off to the next
    ->sendIt((object) ['id' => 'clk_134', 'ip' => '203.0.113.9', 'userAgent' => 'Mozilla/5.0']);

implode(', ', $fired);   // 'BotChecksStarted, RuleStarting(DetectHostingProviderIp), RuleFinished(DetectHostingProviderIp), RuleStarting(MatchKnownBotUserAgents), RuleFinished(MatchKnownBotUserAgents), BotChecksFinished'
```

`before` and `after` bracket the rules, `beforeEach`/`afterEach` surround each rule in turn, and a skipped hub dispatches only the skip event. A rule that stops the hub never hands off, so its `afterEach` does not fire; rules skipped without running (by `skipIf()`, `skipAll()` or the rule's own skip flag) fire neither, while a rule that runs and returns `$this->skip()` fires both like any other. Every dispatch is written to the log under `PipeHub:event`.

### Rule copies: shared and transient bindings

Each run resolves the rule from the container. An ordinary (transient) binding runs exactly as the container returned it. A rule bound as shared (`singleton()`, `instance()`, `scoped()`, or the container's `#[Singleton]`/`#[Scoped]` attributes on Laravel versions that have them) runs as a clone, so the shared object is never executed and the rule's own scalar settings never carry from one run to the next. PHP clones are shallow: an object held in a property of a shared rule is still shared, so give the rule a `__clone()` that copies it if each run needs its own. A shared rule that cannot be cloned (a private `__clone()`, or one that throws) is a configuration error: the flow throws a `PipeHubException` telling you to bind it non-shared, rather than silently skipping the rule. Keep anything that must outlive a run in an injected service. A hub given its own container (`PipeHub::create($container)`) runs its rules, callbacks and events in that container. An envelope you created yourself and passed in keeps the container it was created with for its own services, such as a persistence strategy bound in the container.

### JSON-safe log values

Every value is made JSON-safe as it is recorded, so a value a rule returned can never make the log fail to encode and hide what the flow did. (Saving can still fail for ordinary reasons such as the database being down; those errors are not swallowed.) A rule may return anything, but `NAN` and `INF` are logged as the strings `"NAN"` / `"INF"`, resources as `"resource(stream)"`, closures as `"Closure"`, backed enums as their value and unit (pure) enums as their name, dates as ISO 8601 strings, exceptions as their class and message, other objects as their public properties plus a `__class` key (or what their `jsonSerialize()`, `toArray()` or `__toString()` gives), cycles as `"[recursion: Class]"`, nesting past 16 levels as `"[max depth]"`, and invalid UTF-8 bytes are replaced. The raw value is still what `$envelope->get(Rule::class)` and `$envelope->results()` return.

### Saving logs

Once a persistence strategy is set up, a manager saves the log once, when the flow ends, whether it completed, stopped early or threw; a hub run on its own saves when it finishes. `->persistLogs(false)` turns that off, so you can call `$envelope->persist()` yourself. A run is saved with the strategy given to `$envelope->persistWith()`, else the one bound to `PipeLoggerPersistStrategy` in the container (bind `DatabasePersistStrategy` there once to use it for every flow); with neither, nothing is written.

The `pipe_dream_logs` row holds the run id, the flow's name, the log (JSON), `has_errors` / `has_fatals`, and the subject's type and id when the subject is a saved Eloquent model. It is written with a single upsert keyed on the run id. The query scopes `PipeDreamLog::forRun($runId)->first()` (one run) and `PipeDreamLog::withProblems()->get()` (runs with errors or fatals) find runs, and `logger()` rebuilds a run's records. `new DatabasePersistStrategy(new PipeLoggerSerializeStrategy())` stores PHP `serialize()` output instead of JSON (rebuilt with `allowed_classes => false`, so a stored log can never instantiate objects); pass the same strategy to `logger()` to read it back. To store logs somewhere else, implement `PipeLoggerPersistStrategy`, which has one method, `persist(PipeEnvelope $subject)`; `$subject->logger()->output()` gives you the log as JSON.

### Introspecting a flow

Tools can read a flow back without reflection, which is how the demo renders its flow page from the same objects that process the clicks: `PipeManager::getName()`, `getDescription()`, `getHubs()`, `getThen()`, `getOnError()`, `getOnFatal()`, `throwsFatals()`, `persistsLogs()`; `PipeHub::getName()`, `getDescription()`, `getPipes()`, `getSkipIf()`, `getThen()`, `getOnError()`, `getOnFatal()`, `getEvents()`, `stopsOnlyThisHubOnFatal()`, `treatsErrorsAsFatal()`, `isSkipped()`, `throwsFatals()`, `persistsLogs()`.

### Compatibility

Tested on Laravel 9–13 (CI matrix) with PHP 8.1–8.4, each Laravel major on the PHP versions it supports: Laravel 9 on 8.1–8.2, 10 on 8.1–8.3, 11 and 12 on 8.2–8.4, 13 on 8.3–8.4.

### Testing

```bash
composer install
composer test
```

The suite uses Orchestra Testbench, and its database tests run on in-memory SQLite. Every PHP block in this README is a complete file in `docs/examples/`, shown exactly as it is; `tests/Feature/ReadmeExamplesTest.php` runs each script and loads each class just as shown, checks every output shown and every `// value` comment.

### Changelog and license

See [CHANGELOG.md](CHANGELOG.md) for what changed. MIT licensed; see [LICENSE](LICENSE).
