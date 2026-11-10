<?php

use Illuminate\Http\Request;
use Keaton\PipeDream\Models\PipeDreamLog;

GeoIp::$slowLookups = [6.0, 6.0, 4.8];          // the GeoIP provider is having a bad day

$click = Request::create('/click', 'GET', [
    'click_id' => 'clk_202',
    'campaign' => 'spring-sale',
    'pub' => 'pub-12',                          // no country on this link, so we ask GeoIP
], [], [], [
    'REMOTE_ADDR' => '198.51.100.7',
    'HTTP_USER_AGENT' => 'HeadlessChrome SEO-bot/2.1',
]);

app(HandleAdClick::class)($click);

echo PipeDreamLog::latest('id')->first()->receipt();
