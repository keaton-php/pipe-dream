<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$flow = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->description('Make the title presentable')
        ->addPipes([TidyTitle::class, CountWords::class]),

    PipeHub::create()
        ->name('Publish')
        ->description('Give it a slug')
        ->addPipes([SlugifyTitle::class])
        ->fatalsStopThisHubOnly(),
])->name('Publish a post');

echo $flow->getName(), PHP_EOL;

foreach ($flow->getHubs() as $hub) {
    echo '- ', $hub->getName(), ': ', $hub->getDescription(), ' (pipes: ', count($hub->getPipes()), ')';
    echo $hub->stopsOnlyThisHubOnFatal() ? ', fatals stop only this hub' : '', PHP_EOL;
}
