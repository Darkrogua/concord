<?php namespace Zen\Chub\Classes\System;

class Monitoring
{
    public static function getMemoryUsage()
    {
        return [
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ];
    }
}