<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Illuminate\Contracts\Container\Container;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

/**
 * Starts a second run of itself, through the same container-shared
 * instance, while its own run is still going, with a different policy and
 * container, then reports what its own run still sees.
 */
class ReentrantRule extends PipeRule
{
    public static ?Container $innerContainer = null;

    public function reentrantRule($subject)
    {
        if ($subject->get('inner')) {
            Journal::record('inner ran');

            return self::SUCCESS;
        }

        $inner = PipeEnvelope::wrap(new \stdClass())->set('inner', true);

        PipeHub::create(static::$innerContainer)
            ->addPipe(static::class)
            ->skipIf(fn () => true)
            ->errorsAreFatal()
            ->persistLogs(false)
            ->sendIt($inner);

        Journal::record('outer subject intact: '.($this->subject === $subject ? 'yes' : 'no'));
        Journal::record('outer skipping: '.($this->willSkip($subject) ? 'yes' : 'no'));
        Journal::record('outer errors are fatal: '.($this->policyErrorsAreFatal ? 'yes' : 'no'));
        Journal::record('outer container is the inner one: '.($this->container() === static::$innerContainer ? 'yes' : 'no'));

        $this->info('after the nested run');

        return self::SUCCESS;
    }
}
