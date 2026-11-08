<!-- nav-group: Reference -->
# Rule options

Every setting a rule has, in one list.

## Static settings

| Setting | What it does |
|---|---|
| `public static $name` | The rule's display name. |
| `public static $description` | A one-line description of what the rule does. |
| `public static $runFunction = 'check';` | Runs a method with another name than the class name. |
| `public static $saveResultAs = 'title_verdict';` | Also stores the rule's result under that key. |

## Instance settings

| Setting | What it does |
|---|---|
| `protected $errorsAreFatal = true;` | Turns the rule's `error()` calls and unexpected exceptions into fatals. |
| `$this->foo = $value` / `$this->foo` | Writes and reads `foo` on the envelope while the rule runs. |
| `$this->setFooResult($value)` / `$this->getFooResult()` | The same, as methods. |

## Where the results go

- Rule results are stored on the envelope under the class name: `$envelope->get(TidyTitle::class)`.
- `addPipe()` and `addPipes()` throw a `PipeHubException` for a class that does not exist or is not a `PipeRule`, so a typo never quietly drops a step.

Next: [API](22-api.md).
