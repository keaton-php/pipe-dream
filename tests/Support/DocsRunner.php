<?php

namespace Keaton\PipeDream\Tests\Support;

use LogicException;

/**
 * Runs the PHP blocks of the README and the docs pages as written.
 *
 * A block that starts with a class, an interface or an enum is a definition. It is written
 * to a file of its own and loaded once, before any script runs, and a block that repeats a
 * definition must match the first one exactly. Any other PHP block is a script. A script is
 * run, and the `text` block that follows it is what the script has to print. A script with
 * no `text` block has to print nothing.
 *
 * The README and the introduction open with pieces of an app, not with whole programs: a
 * receipt, the flow it came from, two queries on saved runs, and a second receipt. Those
 * four blocks are the opening. They are not run as scripts. DocsExamplesTest runs each of
 * them inside the code it leaves out.
 *
 * The classes the pages use but do not show are in tests/Fixtures/Docs.
 */
final class DocsRunner
{
    private const ROOT = __DIR__.'/../..';

    private const FIXTURES = __DIR__.'/../Fixtures/Docs';

    /** The sources that start with the opening. */
    private const OPENS_WITH_A_STORY = ['README.md', 'docs/index.md'];

    /** The languages of the four blocks of the opening, in order. */
    private const OPENING = ['text', 'php', 'php', 'text'];

    private static bool $booted = false;

    /** @var array<string, string> class name => the block that declared it */
    private static array $declared = [];

    private static ?string $directory = null;

    /** @return array<string, string> source name => its text: the README, then index.md, then the numbered pages */
    public static function sources(): array
    {
        return ['README.md' => (string) file_get_contents(self::ROOT.'/README.md')] + self::pages();
    }

    /** @return array<string, string> page name => its text: index.md first, then the numbered pages */
    public static function pages(): array
    {
        $files = glob(self::ROOT.'/docs/*.md') ?: [];

        usort($files, fn (string $a, string $b) => [basename($a) !== 'index.md', basename($a)] <=> [basename($b) !== 'index.md', basename($b)]);

        $pages = [];

        foreach ($files as $file) {
            $pages['docs/'.basename($file)] = (string) file_get_contents($file);
        }

        return $pages;
    }

    /**
     * The opening of the README or of the introduction.
     *
     * @return array{receipt: string, flow: string, queries: string, checkout: string}
     */
    public static function opening(string $markdown): array
    {
        $found = array_slice(self::fences($markdown), 0, count(self::OPENING));

        if (array_column($found, 1) !== self::OPENING) {
            throw new LogicException('The opening is a receipt, a flow, two queries and a receipt, in that order');
        }

        return array_combine(['receipt', 'flow', 'queries', 'checkout'], array_column($found, 2));
    }

    /**
     * The code blocks of one source that are run as written, in order: every block, except
     * the opening of the README and of the introduction.
     *
     * @return list<array{kind: string, code: string, output: ?string}>
     */
    public static function blocks(string $name, string $markdown): array
    {
        $found = self::fences($markdown);

        if (in_array($name, self::OPENS_WITH_A_STORY, true)) {
            $found = array_slice($found, count(self::OPENING));
        }

        $blocks = [];

        foreach ($found as [, $language, $code]) {
            if ($language === 'php') {
                $blocks[] = ['kind' => self::isDefinition($code) ? 'definition' : 'script', 'code' => $code, 'output' => null];

                continue;
            }

            $last = array_key_last($blocks);

            if ($language === 'text' && $last !== null && $blocks[$last]['kind'] === 'script' && $blocks[$last]['output'] === null) {
                $blocks[$last]['output'] = $code;

                continue;
            }

            $blocks[] = ['kind' => $language, 'code' => $code, 'output' => null];
        }

        return $blocks;
    }

    /** @return array<string, array{string, ?string}> "page #n" => [the script, the output shown under it] */
    public static function scripts(): array
    {
        $scripts = [];

        foreach (self::sources() as $name => $markdown) {
            $number = 0;

            foreach (self::blocks($name, $markdown) as $block) {
                if ($block['kind'] === 'script') {
                    $scripts[$name.' #'.++$number] = [$block['code'], $block['output']];
                }
            }
        }

        return $scripts;
    }

    /**
     * The test methods of the test classes the pages show.
     *
     * @return array<string, array{class-string, string}> "Class::method" => [the class, the method]
     */
    public static function tests(): array
    {
        $tests = [];

        foreach (self::sources() as $name => $markdown) {
            foreach (self::blocks($name, $markdown) as $block) {
                if ($block['kind'] !== 'definition' || preg_match('/^class (\w+Test) extends /m', $block['code'], $class) !== 1) {
                    continue;
                }

                $namespace = preg_match('/^namespace ([^;]+);/m', $block['code'], $found) === 1 ? $found[1].'\\' : '';

                preg_match_all('/^    public function (test\w+)\(/m', $block['code'], $methods);

                foreach ($methods[1] as $method) {
                    $tests[$class[1].'::'.$method] = [$namespace.$class[1], $method];
                }
            }
        }

        return $tests;
    }

    /** Load the fixture classes, then every class the pages show. */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        foreach (self::fixtureFiles() as $file) {
            require_once $file;
        }

        foreach (self::sources() as $name => $markdown) {
            foreach (self::blocks($name, $markdown) as $block) {
                if ($block['kind'] === 'definition') {
                    self::declare($name, $block['code']);
                }
            }
        }

        self::$booted = true;
    }

    /**
     * Run one script and return what it printed. A piece of the opening is run between the
     * code that comes before it in an app and the code that comes after it.
     */
    public static function run(string $script, string $before = '', string $after = ''): string
    {
        self::boot();

        ob_start();

        try {
            (static function (string $__code): void {
                eval($__code);
            })($before."\n".preg_replace('/\A<\?php\s*/', '', $script)."\n".$after);
        } finally {
            $printed = (string) ob_get_clean();
        }

        return $printed;
    }

    /** @return list<string> the classes the pages show, with their namespace */
    public static function declaredClasses(): array
    {
        self::boot();

        return array_keys(self::$declared);
    }

    /** @return list<string> the classes of tests/Fixtures/Docs, without namespace */
    public static function fixtureClasses(): array
    {
        $classes = [];

        foreach (self::fixtureFiles() as $file) {
            preg_match_all('/^(?:final |abstract )?(?:class|enum|interface) (\w+)/m', (string) file_get_contents($file), $found);

            array_push($classes, ...$found[1]);
        }

        return $classes;
    }

    /** Take out what differs from run to run: timings, durations and where the files are. */
    public static function normalize(string $output): string
    {
        $output = str_replace([self::directory().'/', (string) realpath(self::ROOT).'/'], '', $output);
        $output = (string) preg_replace('/\(\d+(?:\.\d+)? ?ms\)/', '(ms)', $output);
        $output = (string) preg_replace('/"duration_ms": [\d.]+/', '"duration_ms": 0', $output);
        $output = (string) preg_replace('/ {2,}/', ' ', $output);

        return trim($output);
    }

    /** @return list<array{string, string, string}> every fenced block: the whole match, its language, its code */
    private static function fences(string $markdown): array
    {
        preg_match_all('/^```(\w+)\n(.*?)\n```$/ms', $markdown, $found, PREG_SET_ORDER);

        return $found;
    }

    /** @return list<string> */
    private static function fixtureFiles(): array
    {
        return glob(self::FIXTURES.'/*.php') ?: [];
    }

    private static function isDefinition(string $code): bool
    {
        return preg_match('/\A<\?php\n\n(?:namespace [^\n]+\n\n)?(?:use [^\n]+\n)*\n?(?:final |abstract )?(?:class|enum|interface) /', $code) === 1;
    }

    private static function declare(string $page, string $code): void
    {
        $namespace = preg_match('/^namespace ([^;]+);/m', $code, $found) === 1 ? $found[1].'\\' : '';

        preg_match_all('/^(?:final |abstract )?(?:class|enum|interface) (\w+)/m', $code, $classes);

        $first = $namespace.$classes[1][0];

        if (isset(self::$declared[$first])) {
            if (self::$declared[$first] !== $code) {
                throw new LogicException("{$page} shows {$first} differently from an earlier page");
            }

            return;
        }

        foreach ($classes[1] as $class) {
            if (isset(self::$declared[$namespace.$class]) || class_exists($namespace.$class, false)) {
                throw new LogicException("{$page} declares {$namespace}{$class}, which already exists");
            }

            self::$declared[$namespace.$class] = $code;
        }

        // A file of its own, so a receipt that shows where a rule failed points at the
        // line of the block as the page shows it.
        $file = self::directory().'/'.$classes[1][0].'.php';

        file_put_contents($file, $code."\n");

        require $file;
    }

    private static function directory(): string
    {
        if (self::$directory !== null) {
            return self::$directory;
        }

        $directory = sys_get_temp_dir().'/pipe-dream-docs-'.getmypid();

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        register_shutdown_function(static function () use ($directory): void {
            foreach (glob($directory.'/*.php') ?: [] as $file) {
                unlink($file);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        });

        return self::$directory = $directory;
    }
}
