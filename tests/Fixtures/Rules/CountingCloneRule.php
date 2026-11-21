<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

/** A rule whose public __clone() does its own work on every copy. */
class CountingCloneRule extends PipeRule
{
    public static int $copies = 0;

    public ?int $copyNumber = null;

    public function __clone()
    {
        $this->copyNumber = ++static::$copies;
    }

    public function countingCloneRule($subject)
    {
        Journal::record('copy '.($this->copyNumber ?? 'original'));

        return self::SUCCESS;
    }
}
