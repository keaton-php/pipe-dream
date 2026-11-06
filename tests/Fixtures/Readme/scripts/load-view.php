<?php

use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChallengeGraylistedPublishers;

// README:start
$click = (object) ['id' => 'clk_130', 'publisher' => 'pub-77'];

try {
    PipeHub::create()
        ->name('Blacklist & graylist')
        ->addPipes([ChallengeGraylistedPublishers::class])
        ->sendIt($click);
} catch (PipeRuleLoadView $e) {
    return view($e->view, $e->data);      // the 'challenge' view, with ['click' => 'clk_130']
}
// README:end
