<!-- nav-group: Controlling the flow -->
# Redirects and views

How does a rule hand control back to the controller, and how does the controller read the result?

## Hand off from a rule

A rule can hand control back to your controller: throwing `PipeRuleRedirect` (`PipeRuleRedirect::toUrl($url)` or `::toRoute($name)`) or `PipeRuleLoadView` (`PipeRuleLoadView::load($view, $data)`) ends the flow and leaves `sendIt()` as that exception. One thrown from `then()`, `onError()` or `onFatal()` does the same.

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

## In a controller

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

Run it for three posts: a ready one, an empty one and a draft:

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

## Quiet or thrown

To get the fatal as an exception instead, call `throwFatals()` on the manager (or on a hub you run on its own). When a fatal stops the flow, `sendIt()` throws that same `PipeRuleFatal` (unless an `onFatal()` hook hands off a redirect or view, or throws a configuration error, which leaves instead), but only after everything that happens without it: the fatal is logged, `onFatal()` runs, `then()` does not run and the log is persisted just as it would be without it (see [Saving logs to the database](13-persisting.md): with `persistLogs(false)`, or no strategy set, nothing is saved, and an exception from saving takes the fatal's place). `$e->subject()` is the envelope. A fatal contained by `fatalsStopThisHubOnly()` is never thrown, because the flow carried on:

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

Next: [Saving logs to the database](13-persisting.md).
