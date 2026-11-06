<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

class SuppressionList
{
    public function contains(?string $email): bool
    {
        return in_array($email, ['opted-out@example.test'], true);
    }
}
