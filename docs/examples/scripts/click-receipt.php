<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Models\PipeDreamLog;

$click = Request::create('/click', 'GET', ['click_id' => 'clk_201', 'campaign' => 'spring-sale', 'pub' => 'pub-12', 'country' => 'US'], [], [], [
    'REMOTE_ADDR' => '198.51.100.7',
    'HTTP_USER_AGENT' => 'HeadlessChrome SEO-bot/2.1',
]);

app(HandleAdClick::class)($click);

echo PipeDreamLog::latest('id')->first()->receipt();
