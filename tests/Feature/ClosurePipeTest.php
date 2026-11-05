<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\ClosurePipe;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class ClosurePipeTest extends TestCase
{
    public function test_a_closure_pipe_runs_and_the_chain_continues(): void
    {
        $line = __LINE__ + 1;
        $closure = fn ($subject) => Journal::record('closure');

        $envelope = PipeManager::create([
            PipeHub::create()
                ->addPipes([FirstRule::class, $closure, SecondRule::class])
                ->then(fn () => Journal::record('then')),
            PipeHub::create()->addPipe(ThirdRule::class),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(['FirstRule', 'closure', 'SecondRule', 'then', 'ThirdRule'], Journal::$entries);

        $key = ClosurePipe::keyFor($closure);
        $this->assertSame('Closure@ClosurePipeTest.php:'.$line, $key);
        $this->assertSame(['timerStart', 'returnValue', 'timerEnd'], $this->typesFor($envelope, $key));
        $this->assertSame(PipeRule::SUCCESS, $envelope->get($key));
    }

    public function test_a_closure_pipe_return_value_is_its_result(): void
    {
        $closure = fn ($subject) => PipeRule::FAIL;

        $envelope = PipeHub::create()->addPipes([$closure, FirstRule::class])->sendIt(new stdClass());

        $this->assertSame(PipeRule::FAIL, $envelope->get(ClosurePipe::keyFor($closure)));
        $this->assertSame(['FirstRule'], Journal::$entries);
    }

    public function test_a_closure_pipe_that_returns_the_subject_succeeds(): void
    {
        $closure = fn ($subject) => $subject;

        $envelope = PipeHub::create()->addPipes([$closure, FirstRule::class])->sendIt(new stdClass());

        $this->assertSame(PipeRule::SUCCESS, $envelope->get(ClosurePipe::keyFor($closure)));
        $this->assertSame(['FirstRule'], Journal::$entries);
    }

    public function test_a_closure_pipe_receives_the_envelope_and_dependencies(): void
    {
        $seen = null;

        PipeHub::create()
            ->addPipe(function ($click, \Illuminate\Contracts\Config\Repository $config) use (&$seen) {
                $seen = [$click->subject()->id, $config->get('database.default')];
            })
            ->sendIt((object) ['id' => 7]);

        $this->assertSame([7, 'sqlite'], $seen);
    }

    public function test_a_fatal_in_a_closure_pipe_stops_the_hub_like_a_rule(): void
    {
        $closure = function () {
            throw new PipeRuleFatal('No campaign');
        };

        $envelope = PipeHub::create()->addPipes([$closure, FirstRule::class])->sendIt(new stdClass());

        $this->assertSame([], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ClosurePipe::keyFor($closure)));
        $this->assertTrue($envelope->hasFatalErrors());
    }
}
