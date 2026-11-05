<?php

namespace Keaton\PipeDream\Tests\Unit;

use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\InjectedRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\MagicResultRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\NamedParameterRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\NoMethodRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\RunFunctionRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SavesResultAsRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\TestCase;
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

    public function test_a_rule_method_gets_the_subject_whatever_its_parameter_is_called(): void
    {
        PipeHub::create()->addPipe(NamedParameterRule::class)->sendIt((object) ['id' => 9]);

        $this->assertSame(['click 9 at 21'], Journal::$entries);
    }

    public function test_apply_policy_replaces_the_whole_previous_policy(): void
    {
        // Hubs run a fresh copy per run, but the policy write must stand on
        // its own too: nothing from an earlier call survives the next one.
        $rule = new FirstRule();
        $envelope = new PipeEnvelope(new stdClass());

        $rule->applyPolicy(true, 'skipped by the first hub', true);
        $this->assertTrue($rule->willSkip($envelope));

        $rule->applyPolicy(false, null, false);
        $this->assertFalse($rule->willSkip($envelope));

        PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->sendIt($envelope);
        $this->assertSame(['ErrorRule', 'SecondRule'], Journal::$entries);
    }

    public function test_a_rule_without_a_run_method_is_logged_as_a_failure(): void
    {
        $envelope = PipeHub::create()->addPipe(NoMethodRule::class)->sendIt(new stdClass());

        $this->assertSame([], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(NoMethodRule::class));
        $this->assertStringContainsString('has no run method', $envelope->logger()->errors()->first()['data']);
    }
}
