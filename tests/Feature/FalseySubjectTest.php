<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeHubException;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\TestCase;

/**
 * Only null means "no subject". false, 0, '' and [] are valid subjects for
 * a standalone hub, exactly as they are for a manager.
 */
class FalseySubjectTest extends TestCase
{
    private const FALSEY = ['false' => false, 'zero' => 0, 'empty string' => '', 'empty array' => []];

    public function test_a_standalone_hub_accepts_falsey_subjects(): void
    {
        foreach (self::FALSEY as $label => $value) {
            $envelope = PipeHub::create()->addPipe(FirstRule::class)->sendIt($value);

            $this->assertSame($value, $envelope->subject(), $label);
        }

        $this->assertSame(array_fill(0, 4, 'FirstRule'), Journal::$entries);
    }

    public function test_a_hub_given_a_falsey_subject_up_front_accepts_it(): void
    {
        foreach (self::FALSEY as $label => $value) {
            $envelope = PipeHub::create()->addPipe(FirstRule::class)->subject($value)->sendIt();

            $this->assertSame($value, $envelope->subject(), $label);
        }

        $this->assertCount(4, Journal::$entries);
    }

    public function test_a_manager_accepts_falsey_subjects(): void
    {
        foreach (self::FALSEY as $label => $value) {
            $envelope = PipeManager::create([PipeHub::create()->addPipe(FirstRule::class)])->subject($value)->sendIt();

            $this->assertSame($value, $envelope->subject(), $label);
        }

        $this->assertCount(4, Journal::$entries);
    }

    public function test_null_is_still_a_missing_subject(): void
    {
        $this->expectException(PipeHubException::class);

        PipeHub::create()->addPipe(FirstRule::class)->sendIt(null);
    }
}
