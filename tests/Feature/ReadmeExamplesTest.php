<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Tests\TestCase;

/**
 * The code examples in README.md, run as written.
 *
 * The README is a short pitch: it shows one flow, and the receipt it prints. Each
 * PHP block in it is preceded by an `<!-- example: path -->` marker naming a
 * complete file under docs/examples, and shows that file exactly; each output
 * block is preceded by an `<!-- output: path -->` marker and shows exactly what
 * that script prints. Every other example lives on a docs page and is checked by
 * DocsExamplesTest.
 */
class ReadmeExamplesTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    private const FIXTURES = self::ROOT.'/docs/examples';

    public function test_every_example_in_the_readme_is_its_fixture_verbatim(): void
    {
        preg_match_all('/<!-- example: (\S+) -->\n```php\n(.*?)\n```/s', $this->readme(), $examples, PREG_SET_ORDER);

        $this->assertGreaterThanOrEqual(1, count($examples));

        foreach ($examples as [, $fixture, $shown]) {
            $this->assertSame(rtrim(file_get_contents(self::ROOT.'/'.$fixture), "\n"), $shown, "README example {$fixture} differs from its file");
        }
    }

    public function test_every_php_block_in_the_readme_is_backed_by_a_fixture(): void
    {
        preg_match_all('/(.*)\n```php\n/', $this->readme(), $blocks);

        $this->assertGreaterThanOrEqual(1, count($blocks[1]));

        foreach ($blocks[1] as $lineBefore) {
            $this->assertMatchesRegularExpression('/^<!-- example: \S+ -->$/', $lineBefore, 'A PHP block in the README has no example marker');
        }
    }

    public function test_every_output_shown_is_what_its_example_prints(): void
    {
        preg_match_all('/<!-- output: docs\/examples\/scripts\/(\S+) -->\n```text\n(.*?)\n```/s', $this->readme(), $outputs, PREG_SET_ORDER);

        $this->assertGreaterThanOrEqual(1, count($outputs));

        foreach ($outputs as [, $script, $shown]) {
            ob_start();
            $this->runScript($script);
            $printed = ob_get_clean();

            $this->assertSame($this->withoutTimes($shown), $this->withoutTimes($printed), "Output of {$script}");
        }
    }

    public function test_the_hub_tidies_the_title(): void
    {
        ob_start();
        ['post' => $post] = $this->runScript('hub.php');
        ob_end_clean();

        $this->assertSame('Hello, Pipe Dream', $post->title);
    }

    public function test_every_step_is_logged_with_its_return_value(): void
    {
        ob_start();
        $vars = $this->runScript('hub-log.php');
        ob_end_clean();

        $this->assertSame(
            ['hello,   PIPE dream', 'hello, PIPE dream', 'Hello, Pipe Dream'],
            $vars['envelope']->results()->map->result()->values()->all()
        );
    }

    public function test_the_log_points_at_the_step_that_broke_the_title(): void
    {
        ob_start();
        $vars = $this->runScript('hub-log-broken.php');
        ob_end_clean();

        $this->assertSame(
            ['hello,   PIPE dream', 'hello,PIPEdream', 'Hello,pipedream'],
            $vars['envelope']->results()->map->result()->values()->all()
        );
    }

    public function test_the_readme_sends_readers_to_a_docs_page_that_exists(): void
    {
        $this->assertStringContainsString('https://kevineaton.dev/docs/pipe-dream/your-first-flow/', $this->readme());

        // A docs page's address is its file name without the NN- order prefix.
        $this->assertNotEmpty(glob(self::ROOT.'/docs/*-your-first-flow.md'));
    }

    // ------------------------------------------------------------------
    // helpers

    private function readme(): string
    {
        return file_get_contents(self::ROOT.'/README.md');
    }

    /** Run a README script in this test's scope; returns its variables. */
    private function runScript(string $script): array
    {
        $file = self::FIXTURES.'/scripts/'.$script;

        // Every example starts from default fixtures: nothing bound that the
        // README does not show.
        foreach (array_merge($this->fixtureClasses(), [PipeLoggerPersistStrategy::class]) as $class) {
            $this->assertFalse($this->app->bound($class), "{$class} is bound before README example {$script} runs");
        }

        return (function () use ($file) {
            $__result = require $file;
            unset($file);

            return get_defined_vars();
        })->call($this);
    }

    /** @return array<class-string> every class declared under docs/examples */
    private function fixtureClasses(): array
    {
        $classes = [];

        foreach (glob(self::FIXTURES.'/*.php') as $file) {
            preg_match_all('/^class (\w+)/m', file_get_contents($file), $found);
            array_push($classes, ...$found[1]);
        }

        return $classes;
    }

    private function withoutTimes(string $timeline): string
    {
        // A timeline prefix ("0.123 ms  ") and a step's inline "(0.12 ms)" both
        // vary run to run; the rest of the line is what the output promises.
        $timeline = preg_replace('/^\s*\d+\.\d{3} ms  /m', '', $timeline);
        $timeline = preg_replace('/\(\d+(?:\.\d+)? ?ms\)/', '(ms)', $timeline);

        return trim($timeline);
    }
}
