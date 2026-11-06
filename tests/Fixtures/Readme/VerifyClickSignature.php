<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class VerifyClickSignature extends PipeRule
{
    public function verifyClickSignature($click)
    {
        return self::SUCCESS;
    }
}
