<!-- nav-group: Basic usage -->
# Writing rules

How do I turn a step into a named, testable class?

A closure is fine for a small step. A `PipeRule` class gives a step a name, a home and a test. The hub runs the public method named after the class. Inside it, `$this->info('…')` writes a line to the log against the rule, and returning `self::SUCCESS` records that the step passed.

## A rule is a class

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

Add it to a hub by class name, like a closure:

<!-- example: docs/examples/scripts/rule-first.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipe(TidyTitle::class)
    ->sendIt($post);

echo $post->title, PHP_EOL;
echo $envelope->logger()->infos()->firstWhere('key', TidyTitle::class)['data'];
```

<!-- output: docs/examples/scripts/rule-first.php -->
```text
Hello, Pipe Dream
Title is now "Hello, Pipe Dream"
```

The rule's `$name` and `$description` label it, and the log line `$this->info()` wrote is filed under the rule.

## Closure or rule?

| Use a closure when | Use a rule when |
|---|---|
| The step is a line or two | The step has a name your team would use |
| Nothing else reuses it | More than one flow uses it |
| You don't need to test it alone | You want to test it on its own, or inject services into it |

## Remember a value

A rule can leave a value for the rules after it. `CountWords` counts the words in the title and keeps the number; [the next page](07-rule-dependencies.md) explains how the value is stored:

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

## Skip a step or record an error

A rule can decide it has nothing to do and **skip** itself. For a one-word title, this one records `PipeRule::SKIP` (4) and logs its reason. It also uses `$this->error()`, which records a failure (`PipeRule::FAIL`) with its message and, by default, lets the hub carry on to the next rule:

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

What a rule can return, and what each outcome does, is in [the reference table](20-reference-tables.md). How a failure stops a flow is in [Errors, fatals and stopping](10-stopping.md).

Next: [Dependency injection and rule state](07-rule-dependencies.md).
