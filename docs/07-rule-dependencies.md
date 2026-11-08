<!-- nav-group: Basic usage -->
# Dependency injection and rule state

How do I inject a service into a rule, and keep a value between its steps?

## Inject a service

The rule method is called through Laravel's container, so **dependency injection just works**: ask for a class and you get it. Here a rule asks for `Slugger`, a plain class of yours:

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

The first parameter receives **the envelope**, whatever you call it. Leave it untyped, or type it `PipeEnvelope`; a different class type there is resolved from the container instead. Every other parameter is resolved from the container.

## Keep a value between rules

Inside a rule, `$this->words = 3` and `$this->words` set and read a shared value on the envelope (`$this->setWordsResult(3)` and `$this->getWordsResult()` do the same). That is how `CountWords` hands its number to `CheckTitleLength`.

## Run the rules together

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

## Rule copies: shared and transient bindings

Each run resolves the rule from the container. An ordinary (transient) binding runs exactly as the container returned it. A rule bound as shared (`singleton()`, `instance()`, `scoped()`, or the container's `#[Singleton]`/`#[Scoped]` attributes on Laravel versions that have them) runs as a clone, so the shared object is never executed and the rule's own scalar settings never carry from one run to the next.

- PHP clones are shallow: an object held in a property of a shared rule is still shared, so give the rule a `__clone()` that copies it if each run needs its own.
- A shared rule that cannot be cloned (a private `__clone()`, or one that throws) is a configuration error: the flow throws a `PipeHubException` telling you to bind it non-shared, rather than silently skipping the rule.
- Keep anything that must outlive a run in an injected service.
- A hub given its own container (`PipeHub::create($container)`) runs its rules, callbacks and events in that container. An envelope you created yourself and passed in keeps the container it was created with for its own services, such as a persistence strategy bound in the container.

Other rule settings (`$runFunction`, `$saveResultAs`, `$errorsAreFatal`) are on the [rule options page](21-rule-options.md).

Next: [Stacking hubs with a manager](08-managers.md).
