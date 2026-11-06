<?php

use Keaton\PipeDream\PipeRule;

class DetectHostingProviderIp extends PipeRule
{
    public static $name = 'Detect hosting-provider IPs';
    public static $description = 'People browse from homes and phones, not from server racks';

    public function detectHostingProviderIp($click)
    {
        // 198.51.100.0/24 stands in for a cloud provider's range.
        if (str_starts_with((string) $click->ip, '198.51.100.')) {
            $click->set('bot_signals', ($click->get('bot_signals') ?? 0) + 1);
            $this->warning("{$click->ip} belongs to a hosting provider");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
