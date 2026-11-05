<?php

namespace R1kon\PipeDream\Tests\Unit;

use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;
use R1kon\PipeDream\Tests\Fixtures\Rules\InjectedRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\MagicResultRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\NoMethodRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\RunFunctionRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\SavesResultAsRule;
use R1kon\PipeDream\Tests\TestCase;
use stdClass;

class PipeRuleTest extends TestCase
{
    public function test_save_result_as_also_stores_the_result_under_that_key(): void
    {
        $envelope = PipeHub::create()->addPipe(SavesResultAsRule::class)->sendIt(new stdClass());

        $this->assertSame('approved', $envelope->get(SavesResultAsRule::class));
        $this->assertSame('approved', $envelope->get('verdict'));
    }

    public function test_magic_result_accessors_use_the_envelope(): void
    {
        $envelope = PipeHub::create()->addPipe(MagicResultRule::class)->sendIt(new stdClass());

        $this->assertSame(['bot_score=0.25'], Journal::$entries);
        $this->assertSame(0.25, $envelope->get('bot_score'));
    }

    public function test_magic_result_accessors_need_a_running_rule(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new MagicResultRule())->setBotScoreResult(1);
    }

    public function test_an_unknown_method_throws(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new MagicResultRule())->launchRockets();
    }

    public function test_run_function_names_the_method_to_run(): void
    {
        $envelope = PipeHub::create()->addPipe(RunFunctionRule::class)->sendIt(new stdClass());

        $this->assertSame(['RunFunctionRule'], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(RunFunctionRule::class));
    }

    public function test_rule_methods_get_dependencies_from_the_container(): void
    {
        PipeHub::create()->addPipe(InjectedRule::class)->sendIt(new stdClass());

        $this->assertSame(['temperature=21'], Journal::$entries);
    }

    public function test_a_rule_without_a_run_method_is_logged_as_a_failure(): void
    {
        $envelope = PipeHub::create()->addPipe(NoMethodRule::class)->sendIt(new stdClass());

        $this->assertSame([], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(NoMethodRule::class));
        $this->assertStringContainsString('has no run method', $envelope->logger()->errors()->first()['data']);
    }
}
