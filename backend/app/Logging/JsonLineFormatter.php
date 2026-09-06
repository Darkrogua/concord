<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger as MonologLogger;

class JsonLineFormatter
{
    public function __invoke(IlluminateLogger|MonologLogger $logger): void
    {
        $monolog = $logger instanceof IlluminateLogger ? $logger->getLogger() : $logger;

        if (! $monolog instanceof MonologLogger) {
            return;
        }

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true);
        $formatter->includeStacktraces();

        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter($formatter);
        }
    }
}
