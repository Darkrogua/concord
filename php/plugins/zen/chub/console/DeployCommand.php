<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Controllers\Deploy;

/**
 * Единая точка деплоя: тот же вызов, что виджет Dashboard и Chub-команда (scripts/deploy.sh + RC).
 */
class DeployCommand extends Command
{
    protected $signature = 'chub:deploy';

    protected $description = 'Деплой на production (SSH git pull через Deploy::runDeployScript)';

    public function handle(): int
    {
        $result = Deploy::runDeployScript();

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
