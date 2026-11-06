<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class RecordConversionPath extends PipeRule
{
    public function recordConversionPath($click)
    {
        return self::SUCCESS;
    }
}
