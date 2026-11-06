<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;

$publish = function (string $title, ?Closure $check = null, ?Closure $edit = null) {
    $check ??= fn (PipeHub $hub) => $hub;
    $edit ??= fn (PipeHub $hub) => $hub;
    $managerThen = 'skipped';

    $envelope = PipeManager::create([
        $check(PipeHub::create()->name('Check')->addPipe(RejectEmptyTitle::class)),
        $edit(PipeHub::create()->name('Edit')->addPipes([TidyTitle::class, CountWords::class, CheckTitleLength::class])),
        PipeHub::create()->name('Publish')->addPipe(SlugifyTitle::class),
    ])
        ->subject((object) ['title' => $title])
        ->then(function () use (&$managerThen) {
            $managerThen = 'ran';
        })
        ->sendIt();

    $outcome = [PipeRule::SUCCESS => 'ok', PipeRule::FAIL => 'error', PipeRule::FATAL_FAIL => 'FATAL', PipeRule::SKIP => 'skip'];
    $ran = $envelope->results()->map(fn ($result) => $result->rule().' '.$outcome[$result->result()])->implode(', ');

    return "{$ran}. Manager then(): {$managerThen}";
};

$long = 'A title that keeps on going for many more words than any feed will show';

echo "1. error() in Edit:\n   ", $publish($long), PHP_EOL;
echo "2. fatal() in Check:\n   ", $publish(''), PHP_EOL;
echo "3. fatal() in Check, with fatalsStopThisHubOnly():\n   ", $publish('', fn ($hub) => $hub->fatalsStopThisHubOnly()), PHP_EOL;
echo "4. error() in Edit, with errorsAreFatal():\n   ", $publish($long, null, fn ($hub) => $hub->errorsAreFatal()), PHP_EOL;
echo "5. error() in Edit, with errorsAreFatal() and fatalsStopThisHubOnly():\n   ", $publish($long, null, fn ($hub) => $hub->errorsAreFatal()->fatalsStopThisHubOnly()), PHP_EOL;
