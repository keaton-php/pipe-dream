<?php

$click = new Click(
    id: 'clk_48213',
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ip: '198.51.100.7',
    campaign: 'spring-sale',
);

require __DIR__.'/click-intake.php';
echo $envelope->receipt();
