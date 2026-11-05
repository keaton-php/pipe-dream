<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FourthRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SkipAllRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SkipsItselfRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class SkipTest extends TestCase
{
    public function test_skip_if_logs_every_rule_as_skipped_without_running_it(): void
    {
        $subject = new stdClass();
        $subject->newsletter = true;

        $envelope = PipeManager::create([
            PipeHub::create()
                ->name('Suppression')
                ->addPipes([FirstRule::class, SecondRule::class])
                ->skipIf(fn ($click) => $click->newsletter)
                ->then(fn () => Journal::record('then')),
            PipeHub::create()->addPipe(ThirdRule::class),
        ])->subject($subject)->sendIt();

        $this->assertSame(['then', 'ThirdRule'], Journal::$entries);
        $this->assertSame(PipeRule::SKIP, $envelope->get(FirstRule::class));
        $this->assertSame(PipeRule::SKIP, $envelope->get(SecondRule::class));
        $this->assertCount(2, $envelope->results()->skips());
        $this->assertContains('info', $this->typesFor($envelope, FirstRule::class));
    }

    public function test_skip_if_returning_false_runs_the_rules(): void
    {
        PipeHub::create()->addPipe(FirstRule::class)->skipIf(fn () => false)->sendIt(new stdClass());

        $this->assertSame(['FirstRule'], Journal::$entries);
    }

    public function test_skip_skips_the_whole_hub_including_then(): void
    {
        $envelope = PipeManager::create([
            PipeHub::create()->addPipe(FirstRule::class)->skip()->then(fn () => Journal::record('then')),
            PipeHub::create()->addPipe(SecondRule::class),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(['SecondRule'], Journal::$entries);
        $this->assertNull($envelope->get(FirstRule::class));
    }

    public function test_skip_all_skips_every_remaining_pipe_in_every_remaining_hub(): void
    {
        $envelope = PipeManager::create([
            PipeHub::create()->addPipes([FirstRule::class, SkipAllRule::class, SecondRule::class]),
            PipeHub::create()->addPipes([ThirdRule::class, fn () => Journal::record('closure')])->then(fn () => Journal::record('then')),
            PipeHub::create()->addPipe(FourthRule::class),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(['FirstRule', 'SkipAllRule', 'then'], Journal::$entries);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(SkipAllRule::class));

        foreach ([SecondRule::class, ThirdRule::class, FourthRule::class] as $rule) {
            $this->assertSame(PipeRule::SKIP, $envelope->get($rule), $rule);
            $this->assertSame('Skipped: skipAll() was called', $envelope->logger()->infos()->firstWhere('key', $rule)['data']);
        }

        $this->assertCount(4, $envelope->results()->skips());
    }

    public function test_a_rule_can_skip_itself_with_a_reason(): void
    {
        $envelope = PipeHub::create()->addPipe(SkipsItselfRule::class)->sendIt(new stdClass());

        $this->assertSame(PipeRule::SKIP, $envelope->get(SkipsItselfRule::class));
        $this->assertCount(1, $envelope->results());
        $this->assertSame('Nothing to check here', $envelope->logger()->infos()->firstWhere('key', SkipsItselfRule::class)['data']);
    }
}
