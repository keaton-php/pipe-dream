<!-- nav-group: Start here -->
# Your first flow

How do I build a flow and read what happened? Four short steps, one running example: tidy a post title.

## 1. Start from a plain pipeline

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

## 2. Run the same steps in a hub

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

No `$next` to call and nothing to return: the hub moves on to the next pipe by itself. Same three steps, same result.

Coming from Laravel's `Pipeline`:

| Laravel `Pipeline` | `PipeHub` |
|---|---|
| `->send($post)->through([...])->thenReturn()` | `->addPipes([...])->sendIt($post)` |
| A pipe calls `$next($post)` to carry on | Nothing to call: the hub carries on |
| The answer is the value you return | The answer is on your object; `sendIt()` returns an envelope holding the results and the log |

## 3. Ask it what happened

If the title came out wrong, a plain pipeline leaves you guessing which step broke it. The hub kept notes:

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

## 4. Break it on purpose

Change the second step so it eats every space, run the hub again, and ask the steps which one lost them. `$envelope->steps()` is the run as a list — one step per pipe, in order — and `first()` reads down it for you:

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

$culprit = $envelope->steps()->first(fn ($step) => ! str_contains($step->returnValue(), ' '));

echo "The spaces vanish at {$culprit->name()}: {$culprit->returnValue()}";
```

<!-- output: docs/examples/scripts/hub-log-broken.php -->
```text
The spaces vanish at Closure@hub-log-broken.php:10: hello,PIPEdream
```

You don't have to guess which step ate the spaces. `steps()->first(...)` hands you the first step whose output lost them: the second one. [Reading the log](05-reading-the-log.md) goes deeper on steps.

A pipeline tells you the answer. A hub hands you the receipt.

## Next steps

- [Pipes and hubs](03-pipes-and-hubs.md): name a hub, add steps, send a subject.
- [Writing rules](06-rules.md): turn a step into a named, testable class.
- [Stacking hubs with a manager](08-managers.md): run several hubs as one flow with one log.
- [Errors, fatals and stopping](10-stopping.md): decide what a failure does.
- [Saving logs to the database](13-persisting.md): keep every run and query it later.
