<?php

$flow = CheckoutFlow::build();

echo $flow->getName(), PHP_EOL;

echo collect($flow->getHubs())
    ->map(fn ($hub) => sprintf(
        "  %s: %s (%d rules%s)",
        $hub->getName(),
        $hub->getDescription(),
        count($hub->getPipes()),
        $hub->getSkipIf() ? ', can be skipped' : '',
    ))
    ->implode(PHP_EOL);
