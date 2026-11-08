<!-- nav-group: Basic usage -->
# Pipes and hubs

How do I add steps to a hub and send a subject through it?

<!-- example: docs/examples/scripts/hub-named.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->name('Tidy the title')
    ->description('Make the title presentable')
    ->addPipe(fn ($post) => $post->title = trim($post->title))
    ->addPipes([
        fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

echo $envelope->flowName(), ': ', $post->title;
```

<!-- output: docs/examples/scripts/hub-named.php -->
```text
Tidy the title: Hello, Pipe Dream
```

A hub standing on its own is the whole flow, so its name becomes the flow's name (`$envelope->flowName()`).

## Create a hub

`PipeHub::create()` makes an empty hub. It resolves rules from Laravel's container; pass your own container to `create()` to use a different one.

## Add pipes

`addPipe()` adds one step and `addPipes()` adds a list. A pipe is a closure or the class name of a [rule](06-rules.md). Closures and rules mix freely, and the hub runs them in the order you added them. A class that does not exist, or is not a `PipeRule`, throws a `PipeHubException` straight away, so a typo never quietly drops a step.

## Name and describe it

`name()` and `description()` label the hub. They show up in the log, and in [introspection](17-introspection.md) when you read a flow back.

## Send a subject

`sendIt($subject)` runs the hub and returns the [envelope](04-envelope.md). The subject can be any value except `null`. Closure and rule results are on the envelope, and so is the log.

## Everything a hub can do

| Method | What it does |
|---|---|
| `name($name)`, `description($text)` | Label the hub. |
| `addPipe($pipe)`, `addPipes($pipes)` | Add a closure or a rule class. |
| `skipIf($callback)`, `skip()` | [Skip](09-skipping.md) the hub's rules, or the whole hub. |
| `then($callback)` | Run a callback after the hub's last rule. |
| `fatalsStopThisHubOnly()`, `errorsAreFatal()` | Decide [how far a failure reaches](10-stopping.md). |
| `onError($callback)`, `onFatal($callback)` | [React to failures](11-error-hooks.md). |
| `throwFatals()`, `persistLogs()` | Throw a stop as an exception; control [saving](13-persisting.md). |
| `broadcastEvent…()` | Dispatch [events](16-events.md) around the hub's rules. |
| `sendIt($subject)` | Run the hub. |

The whole list, with return values, is on the [API page](22-api.md).

Next: [The envelope](04-envelope.md).
