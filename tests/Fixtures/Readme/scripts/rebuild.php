<?php

use Keaton\PipeDream\Models\PipeDreamLog;

// README:start
$run = PipeDreamLog::forRun($envelope->runId())->firstOrFail();

$run->flow;                               // 'Ad click intake'
$run->has_fatals;                         // false
$run->logger()->records() == $envelope->logs();   // true
// README:end
