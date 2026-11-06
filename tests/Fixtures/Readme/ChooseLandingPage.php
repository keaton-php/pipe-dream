<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class ChooseLandingPage extends PipeRule
{
    public static $name = 'Choose the landing page';
    public static $description = 'Send the visitor to the campaign\'s page';

    public function chooseLandingPage($click)
    {
        $click->destination = "https://example.test/{$click->campaign}";

        return self::SUCCESS;
    }
}
