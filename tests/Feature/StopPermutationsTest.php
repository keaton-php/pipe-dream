<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ExplodingRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Every stop permutation: an error, a fatal or an unexpected exception, in
 * a hub run on its own or in the middle hub of three, with and without
 * errorsAreFatal(), fatalsStopThisHubOnly() and throwFatals().
 *
 * The expected run is written from the design, not from the code: an error
 * or exception is a fatal only under errorsAreFatal(); a fatal stops the
 * whole flow unless its hub (inside a manager) uses fatalsStopThisHubOnly();
 * only a fatal that stops the flow is thrown, and only with throwFatals().
 */
class StopPermutationsTest extends TestCase
{
    private const RULES = [
        'error' => ErrorRule::class,
        'fatal' => FatalRule::class,
        'exception' => ExplodingRule::class,
    ];

    public static function permutations(): array
    {
        $cases = [];

        foreach (['error', 'fatal', 'exception'] as $what) {
            foreach (['on its own', 'in a manager'] as $where) {
                foreach ([false, true] as $errorsAreFatal) {
                    foreach ([false, true] as $thisHubOnly) {
                        foreach ([false, true] as $throw) {
                            $label = sprintf(
                                '%s, hub %s, errorsAreFatal %s, fatalsStopThisHubOnly %s, throwFatals %s',
                                $what,
                                $where,
                                $errorsAreFatal ? 'on' : 'off',
                                $thisHubOnly ? 'on' : 'off',
                                $throw ? 'on' : 'off'
                            );

                            $cases[$label] = [$what, $where === 'in a manager', $errorsAreFatal, $thisHubOnly, $throw];
                        }
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @dataProvider permutations
     */
    #[DataProvider('permutations')]
    public function test_every_stop_permutation(string $what, bool $inManager, bool $errorsAreFatal, bool $thisHubOnly, bool $throw): void
    {
        $persist = new CountingPersist();
        $this->app->instance(PipeLoggerPersistStrategy::class, $persist);

        $rule = self::RULES[$what];
        $name = class_basename($rule);
        $hook = fn (string $label) => fn () => Journal::record($label);

        $hub = PipeHub::create()
            ->name('hub')
            ->addPipes([fn () => Journal::record('hub.p1'), $rule, fn () => Journal::record('hub.p3')])
            ->then($hook('hub then'))
            ->onError($hook('hub onError'))
            ->onFatal($hook('hub onFatal'))
            ->errorsAreFatal($errorsAreFatal)
            ->fatalsStopThisHubOnly($thisHubOnly);

        if ($inManager) {
            $run = fn () => PipeManager::create([
                PipeHub::create()->addPipe(fn () => Journal::record('before.p1'))->then($hook('before then')),
                $hub,
                PipeHub::create()->addPipe(fn () => Journal::record('after.p1'))->then($hook('after then')),
            ])
                ->subject(new stdClass())
                ->then($hook('manager then'))
                ->onError($hook('manager onError'))
                ->onFatal($hook('manager onFatal'))
                ->throwFatals($throw)
                ->sendIt();
        } else {
            $run = fn () => $hub->throwFatals($throw)->sendIt(new stdClass());
        }

        // ---- What the design says happens
        $isFatal = $what === 'fatal' || $errorsAreFatal;
        $stopsFlow = $isFatal && ! ($inManager && $thisHubOnly);
        $thrown = $stopsFlow && $throw;

        $expected = $inManager ? ['before.p1', 'before then'] : [];
        $expected[] = 'hub.p1';
        $expected[] = $name;

        if (! $isFatal) {
            array_push($expected, 'hub onError', ...($inManager ? ['manager onError'] : []));
            array_push($expected, 'hub.p3', 'hub then');
        } else {
            $expected[] = 'hub onFatal';

            if ($inManager && $stopsFlow) {
                $expected[] = 'manager onFatal';
            }
        }

        if ($inManager && ! $stopsFlow) {
            array_push($expected, 'after.p1', 'after then', 'manager then');
        }

        // ---- What happened
        $caught = null;

        try {
            $envelope = $run();
        } catch (PipeRuleFatal $e) {
            $caught = $e;
            $persistedBeforeTheCatch = $persist->calls;
            $envelope = $e->subject();
        }

        $this->assertSame($expected, Journal::$entries);
        $this->assertSame($thrown, $caught !== null, $thrown ? 'the fatal should have been thrown' : 'nothing should have been thrown');
        $this->assertSame($isFatal ? PipeRule::FATAL_FAIL : PipeRule::FAIL, $envelope->get($rule));
        $this->assertSame($isFatal, $envelope->hasFatalErrors());
        $this->assertSame(1, $persist->calls);

        if ($caught) {
            $this->assertSame(1, $persistedBeforeTheCatch, 'the log is persisted before the fatal leaves sendIt()');

            if ($what !== 'fatal') {
                $this->assertInstanceOf($what === 'error' ? \Keaton\PipeDream\Exceptions\PipeRuleError::class : \RuntimeException::class, $caught->getPrevious());
            }
        }
    }
}
