<?php

use Keaton\PipeDream\PipeHub;

$click = (object) ['id' => 'clk_131', 'campaign' => 'no-such-campaign'];

$envelope = PipeHub::create()->addPipes([CheckCampaignStatus::class])->sendIt($click);

$envelope->hasErrors();                                  // false
$envelope->hasFatalErrors();                             // true
$envelope->results()->fatalFailures()->count();          // 1
$envelope->logger()->fatals()->first()['data'];          // 'Unknown campaign no-such-campaign [.../CheckCampaignStatus.php | line ...]'
basename($envelope->logger()->fatals()->first()['context']['file']);   // 'CheckCampaignStatus.php'
