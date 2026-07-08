<?php namespace Zen\Chub\Classes\System;

use Zen\Chub\Models\Cron;
use Zen\Chub\Classes\System\ProcessApp;
use Illuminate\Console\Scheduling\Schedule;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\LogsApp;
use Exception;
use Throwable;

class CronApp
{
    public static function make():self
    {
        return new self();
    }

    public function execute(Schedule $schedule)
    {
        $schedules = $this->getCronCache();
        if (!$schedules) {
            $crons = Cron::where('active', 1)->get();
            foreach($crons as $cron) {
                foreach ($cron->planners as $planner) {
                    if (boolval($planner['active'])) {
                        $schedules[] = [
                            'cron' => $planner['cron_command'], # Тут например "* * * * *" что значит запускать каждую минуту
                            'code' => $planner['method_code'] # Код процесса
                        ];
                    }
                }
            }
            $this->setCronCache($schedules);
        }

        $debugging = env('CRONAPP_MODE') === 'log';

        foreach ($schedules as $plan) {
            $schedule->call(function () use ($plan, $debugging) {
                if ($debugging) {
                    $log_message = 'Тестовая фиксация задачи планировщика - Вызов : ' . $plan['code'];
                } else {
                    $log_message = 'Выполнение задания планировщиком: ' . $plan['code'];
                }

                /* Слишком высокий уровень логирования ни к чему
                   впрочем может приголиться в дальнейшем (zenc0dr).
                LogsApp::addInfo([
                    '$plan' => $plan
                ], $log_message);
                */

                if ($debugging) {
                    return;
                }

                try {
                    $method_code = trim((string) ($plan['code'] ?? ''));
                    $command_code = CommandApp::isCommandCode($method_code);
                    if ($command_code) {
                        CommandApp::exec($command_code);
                    } else {
                        ProcessApp::make()->runScheduleProcess($method_code);
                    }
                } catch (Throwable $ex) {
                    LogsApp::addError([
                        'error' => $ex,
                    ], 'Ошибка при выполнении планировщиком задания: ' . $plan['code']);
                }
                
            })->name('chub.schedule.' . $plan['code'])
                ->withoutOverlapping(10)
                ->cron($plan['cron']);
        }
    }

    public function getCronCachePath(): string
    {
        return storage_path('chub/cron/schedules.json');
    }

    public function getCronCache(): array
    {
        if (file_exists($this->getCronCachePath())) {
            return Transformers::make()->arrayFromFile(
                $this->getCronCachePath()
            );
        }
        return [];
    }

    public function setCronCache(array $data): void
    {
        Transformers::make()->arrayToFile(
            $data,
            Files::make()->defineFilePath(
                $this->getCronCachePath()
            )
        );
    }

    public function clearCronCache(): void
    {
        if (file_exists($this->getCronCachePath())) {
            unlink($this->getCronCachePath());
        }
    }
}