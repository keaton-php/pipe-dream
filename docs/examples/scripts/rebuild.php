<?php

use Keaton\PipeDream\Models\PipeDreamLog;

$run = PipeDreamLog::where('flow', 'Ad click intake')->latest()->firstOrFail();

$run->has_fatals;                                                         // false
$run->logger()->fatals()->count();                                        // 0
$run->logger()->infos()->firstWhere('key', ResolveRegion::class)['data'];  // 'Visitor is in North America'
