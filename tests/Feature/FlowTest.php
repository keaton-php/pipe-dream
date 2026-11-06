<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeHubException;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FourthRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class FlowTest extends TestCase
{
    public function test_hubs_and_their_rules_run_in_order(): void
    {
        $envelope = PipeManager::create([
            PipeHub::create()->name('One')->addPipes([FirstRule::class, SecondRule::class]),
            PipeHub::create()->name('Two')->addPipes([ThirdRule::class]),
            PipeHub::create()->name('Three')->addPipes([FourthRule::class]),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(['FirstRule', 'SecondRule', 'ThirdRule', 'FourthRule'], Journal::$entries);
        $this->assertInstanceOf(PipeEnvelope::class, $envelope);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(FourthRule::class));
        $this->assertCount(4, $envelope->results()->successes());
        $this->assertFalse($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
    }

    public function test_every_rule_is_timed_and_its_result_logged(): void
    {
        $envelope = PipeHub::create()->addPipe(FirstRule::class)->sendIt(new stdClass());

        $this->assertSame(['timerStart', 'returnValue', 'timerEnd'], $this->typesFor($envelope, FirstRule::class));
    }

    public function test_hub_and_manager_then_callbacks_run_in_order(): void
    {
        PipeManager::create([
            PipeHub::create()->addPipe(FirstRule::class)->then(fn ($subject) => Journal::record('hub one then')),
            PipeHub::create()->addPipe(SecondRule::class)->then(fn ($subject) => Journal::record('hub two then')),
        ])
            ->subject(new stdClass())
            ->then(fn ($subject) => Journal::record('manager then'))
            ->sendIt();

        $this->assertSame(['FirstRule', 'hub one then', 'SecondRule', 'hub two then', 'manager then'], Journal::$entries);
    }

    public function test_callbacks_receive_the_envelope_whatever_their_parameter_is_called(): void
    {
        $seen = [];

        PipeManager::create([
            PipeHub::create()
                ->addPipe(FirstRule::class)
                ->skipIf(function ($click) use (&$seen) {
                    $seen['skipIf'] = $click;

                    return false;
                })
                ->then(function (PipeEnvelope $envelope) use (&$seen) {
                    $seen['hub then'] = $envelope;
                }),
        ])
            ->subject($subject = new stdClass())
            ->then(function ($s) use (&$seen) {
                $seen['manager then'] = $s;
            })
            ->sendIt();

        $this->assertCount(3, $seen);
        foreach ($seen as $where => $value) {
            $this->assertInstanceOf(PipeEnvelope::class, $value, $where);
            $this->assertSame($subject, $value->subject(), $where);
        }
    }

    public function test_the_envelope_passes_property_access_through_to_the_subject(): void
    {
        $subject = new stdClass();
        $subject->campaign = 'spring-sale';

        $envelope = PipeHub::create()
            ->addPipe(function ($envelope) {
                $envelope->routed = $envelope->campaign.'/landing';
            })
            ->sendIt($subject);

        $this->assertSame('spring-sale/landing', $subject->routed);
        $this->assertSame('spring-sale', $envelope->campaign);
    }

    public function test_a_manager_can_be_created_empty_and_given_hubs_later(): void
    {
        $manager = PipeManager::create()
            ->name('Click intake')
            ->description('From click to route')
            ->addHub($hub = PipeHub::create()->addPipe(FirstRule::class));

        $this->assertSame('Click intake', $manager->getName());
        $this->assertSame('From click to route', $manager->getDescription());
        $this->assertSame([$hub], $manager->getHubs());

        $manager->subject(new stdClass())->sendIt();

        $this->assertSame(['FirstRule'], Journal::$entries);
    }

    public function test_a_hub_exposes_its_configuration(): void
    {
        $skipIf = fn () => false;
        $then = fn () => null;

        $hub = PipeHub::create()
            ->name('Fraud')
            ->description('Is this a person?')
            ->addPipes([FirstRule::class, SecondRule::class])
            ->skipIf($skipIf)
            ->then($then)
            ->fatalsStopThisHubOnly()
            ->errorsAreFatal();

        $this->assertSame('Fraud', $hub->getName());
        $this->assertSame('Is this a person?', $hub->getDescription());
        $this->assertSame([FirstRule::class, SecondRule::class], $hub->getPipes());
        $this->assertSame($skipIf, $hub->getSkipIf());
        $this->assertSame($then, $hub->getThen());
        $this->assertTrue($hub->stopsOnlyThisHubOnFatal());
        $this->assertTrue($hub->treatsErrorsAsFatal());
        $this->assertFalse($hub->isSkipped());
        $this->assertTrue($hub->skip()->isSkipped());

        $bare = PipeHub::create();
        $this->assertNull($bare->getName());
        $this->assertNull($bare->getDescription());
        $this->assertNull($bare->getSkipIf());
        $this->assertFalse($bare->stopsOnlyThisHubOnFatal());
        $this->assertFalse($bare->treatsErrorsAsFatal());
    }

    public function test_a_pipe_that_does_not_exist_throws(): void
    {
        $this->expectException(PipeHubException::class);
        $this->expectExceptionMessage('Pipe [App\Rules\CheckSuppresion] does not exist');

        PipeHub::create()->addPipes([FirstRule::class, 'App\Rules\CheckSuppresion']);
    }

    public function test_a_pipe_that_is_not_a_rule_throws(): void
    {
        $this->expectException(PipeHubException::class);
        $this->expectExceptionMessage('is not a PipeRule');

        PipeHub::create()->addPipe(stdClass::class);
    }

    public function test_a_pipe_that_is_not_a_class_name_or_closure_throws(): void
    {
        $this->expectException(PipeHubException::class);

        PipeHub::create()->addPipe(42);
    }

    public function test_a_hub_that_is_not_a_hub_throws(): void
    {
        $this->expectException(PipeHubException::class);
        $this->expectExceptionMessage('must be PipeHub instances');

        PipeManager::create([PipeHub::create(), FirstRule::class]);
    }

    public function test_running_without_a_subject_throws(): void
    {
        $this->expectException(PipeHubException::class);

        PipeHub::create()->addPipe(FirstRule::class)->sendIt();
    }
}
