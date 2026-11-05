<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;

class ReturnsResourceRule extends PipeRule
{
    public function returnsResourceRule($subject)
    {
        $this->info(['score' => INF, 'ratio' => NAN]);

        return fopen('php://memory', 'r');
    }
}
