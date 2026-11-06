<?php

use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$publish = function (object $post, bool $checkEndsTheFlow) {
    try {
        $envelope = PipeManager::create([
            PipeHub::create()
                ->name('Check')
                ->addPipe(RejectEmptyTitle::class)
                ->stopOnFatal($checkEndsTheFlow),

            PipeHub::create()
                ->name('Publish')
                ->addPipes([TidyTitle::class, SlugifyTitle::class]),
        ])
            ->name('Publish a post')
            ->subject($post)
            ->throwFatals()
            ->sendIt();

        return 'returned, Publish ran: '.var_export(isset($post->slug), true).', fatal on the envelope: '.var_export($envelope->hasFatalErrors(), true);
    } catch (PipeRuleFatal $e) {
        // The exception carries the envelope, its log included.
        return 'caught "'.$e->getMessage().'", Publish ran: '.var_export(isset($post->slug), true).', fatal on the envelope: '.var_export($e->subject()->hasFatalErrors(), true);
    }
};

echo 'Check with stopOnFatal():    ', $publish((object) ['title' => '   '], true), PHP_EOL;
echo 'Check without stopOnFatal(): ', $publish((object) ['title' => '   '], false), PHP_EOL;
