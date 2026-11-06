<?php


// README:start
$t0 = $envelope->logs()[0]['time'];

foreach ($envelope->logs() as $record) {
    printf("%7.3f ms  %-11s %-28s %s\n",
        ($record['time'] - $t0) * 1000, $record['type'], class_basename($record['key']), $record['data']);
}
// README:end
