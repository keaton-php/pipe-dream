<?php

use Keaton\PipeDream\PipeHub;

$click = (object) ['id' => 'clk_123', 'campaign' => 'spring-sale', 'country' => 'CA'];

$envelope = PipeHub::create()
    ->name('Check the click')
    ->addPipes([
        function ($click) {
            $click->set('region', in_array($click->country, ['CA', 'US', 'MX']) ? 'North America' : 'Elsewhere');
        },
        function ($click) {
            $click->destination = "https://example.test/{$click->campaign}";
        },
    ])
    ->sendIt($click);

$t0 = $envelope->logs()[0]['time'];

foreach ($envelope->logs() as $record) {
    printf("%7.3f ms  %-11s %-28s %s\n",
        ($record['time'] - $t0) * 1000, $record['type'], $record['key'], $record['data']);
}
