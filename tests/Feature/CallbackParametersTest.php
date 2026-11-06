<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeRuleError;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ExplodingRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\TestCase;
use LogicException;
use stdClass;

/**
 * Callbacks get the envelope in their first parameter and, for onError()
 * and onFatal(), the exception in their second, whatever those parameters
 * are called, as long as their declared type accepts the value.
 */
class CallbackParametersTest extends TestCase
{
    public function test_zero_and_one_parameter_hooks(): void
    {
        PipeHub::create()
            ->addPipes([ErrorRule::class, FatalRule::class])
            ->onError(fn () => Journal::record('onError()'))
            ->onFatal(fn ($post) => Journal::record('onFatal('.get_class($post).')'))
            ->sendIt(new stdClass());

        $this->assertSame(['ErrorRule', 'onError()', 'FatalRule', 'onFatal('.PipeEnvelope::class.')'], Journal::$entries);
    }

    public function test_union_nullable_and_object_types_receive_the_envelope_and_exception(): void
    {
        $seen = [];

        PipeHub::create()
            ->addPipe(ErrorRule::class)
            ->onError(function (PipeEnvelope|stdClass $post, ?\Throwable $e) use (&$seen) {
                $seen[] = [get_class($post), get_class($e)];
            })
            ->then(function (object $post) use (&$seen) {
                $seen[] = [get_class($post)];
            })
            ->sendIt(new stdClass());

        $this->assertSame([[PipeEnvelope::class, PipeRuleError::class], [PipeEnvelope::class]], $seen);
    }

    public function test_a_scalar_first_parameter_is_not_handed_the_envelope(): void
    {
        $this->app->instance('label', 'from the container');
        $received = null;

        PipeHub::create()
            ->addPipe(fn () => null)
            ->then(function (string $label = 'default') use (&$received) {
                $received = $label;
            })
            ->sendIt(new stdClass());

        $this->assertSame('default', $received);
    }

    public function test_a_second_parameter_typed_as_a_service_is_resolved_from_the_container(): void
    {
        $this->app->instance(Journal::class, $journal = new Journal());
        $received = null;

        PipeHub::create()
            ->addPipe(ErrorRule::class)
            ->onError(function ($post, Journal $service) use (&$received) {
                $received = $service;
            })
            ->sendIt(new stdClass());

        $this->assertSame($journal, $received);
    }

    public function test_an_exception_parameter_of_the_wrong_class_gets_the_real_exception_not_a_new_one(): void
    {
        $received = 'not called';

        // ExplodingRule throws a RuntimeException; the hook asks for a LogicException.
        // PHP refuses the call (the hook itself fails, which stops the hub as a fatal)
        // instead of the container building an unrelated LogicException for it.
        $envelope = PipeHub::create()
            ->addPipe(ExplodingRule::class)
            ->onError(function ($post, LogicException $e) use (&$received) {
                $received = $e;
            })
            ->sendIt(new stdClass());

        $this->assertSame('not called', $received);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(\TypeError::class, $envelope->logger()->fatals()->firstWhere('key', 'PipeHub:sendIt')['context']['exception']);
    }

    public function test_parameters_named_subject_and_exception_are_type_checked_too(): void
    {
        $this->app->instance(Journal::class, $journal = new Journal());
        $seen = [];

        PipeHub::create()
            ->addPipe(ErrorRule::class)
            ->onError(function ($post, Journal $exception) use (&$seen) {
                $seen['service named $exception'] = $exception;
            })
            ->then(function (string $subject = 'default') use (&$seen) {
                $seen['string named $subject'] = $subject;
            })
            ->sendIt(new stdClass());

        $this->assertSame($journal, $seen['service named $exception']);
        $this->assertSame('default', $seen['string named $subject']);
    }

    public function test_intersection_types_must_be_met_in_full(): void
    {
        $binder = new class {
            use \Keaton\PipeDream\Concerns\PassesSubjectToCallbacks;

            public function bind($callback, array $parameters): array
            {
                return $this->withSubjectParameter($callback, $parameters);
            }
        };

        $both = new \ArrayObject();   // Countable & ArrayAccess
        $one = new class implements \Countable {
            public function count(): int
            {
                return 0;
            }
        };

        $accepts = fn (\Countable&\ArrayAccess $value) => null;

        $this->assertSame(['value' => $both], $binder->bind($accepts, ['subject' => $both]));
        $this->assertSame(['subject' => $one], $binder->bind($accepts, ['subject' => $one]));
    }
}
