<?php

use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$flow = fn (object $post, bool $checkStopsOnlyItself = false) => PipeManager::create([
    PipeHub::create()
        ->name('Check')
        ->addPipe(RejectEmptyTitle::class)
        ->fatalsStopThisHubOnly($checkStopsOnlyItself),

    PipeHub::create()
        ->name('Publish')
        ->addPipes([TidyTitle::class, SlugifyTitle::class]),
])
    ->name('Publish a post')
    ->subject($post)
    ->onFatal(function ($post, $fatal) {
        echo '   onFatal(): ', $fatal->getMessage(), PHP_EOL;
    });

echo 'Quiet (the default):', PHP_EOL;
$envelope = $flow((object) ['title' => ''])->sendIt();
echo '   returned, hasFatalErrors() is ', var_export($envelope->hasFatalErrors(), true), PHP_EOL;

echo 'With throwFatals():', PHP_EOL;
try {
    $flow((object) ['title' => ''])->throwFatals()->sendIt();
} catch (PipeRuleFatal $e) {
    // The exception carries the envelope, its log included.
    echo '   caught "', $e->getMessage(), '", hasFatalErrors() is ', var_export($e->subject()->hasFatalErrors(), true), PHP_EOL;
}

echo 'With throwFatals(), the fatal contained to Check:', PHP_EOL;
$post = (object) ['title' => ''];
$envelope = $flow($post, true)->throwFatals()->sendIt();
echo '   returned, Publish ran: ', var_export(isset($post->slug), true), PHP_EOL;
