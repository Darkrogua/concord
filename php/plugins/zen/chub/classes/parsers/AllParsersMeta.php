<?php namespace Zen\Chub\Classes\Parsers;

use Exception;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Classes\System\Stream;

/**
 * Мета-оркестратор: параллельно запускает дочерние flow-оркестраторы парсеров
 * и пишет агрегатный прогресс (batches_total / batches_processed) в state мета-потока.
 */
class AllParsersMeta
{
    public const string META_PROCESS_CODE = 'parser-all-orchestrators';

    /**
     * Коды процессов-оркестраторов из flow_parser-*.json (у каждого свой flow).
     *
     * @var list<string>
     */
    public const array CHILD_ORCHESTRATOR_CODES = [
        'parser-volga-orchestrator',
        'parser-gama-orchestrator',
        'parser-germes-orchestrator',
        'parser-infoflot-orchestrator',
        'parser-waterway-orchestrator',
    ];

    public static function make(): self
    {
        return new self();
    }

    public function run(): void
    {
        $child_count = count(self::CHILD_ORCHESTRATOR_CODES);
        $process_app = ProcessApp::make();

        foreach (self::CHILD_ORCHESTRATOR_CODES as $code) {
            $process_app->runScheduleProcess($code);
        }

        foreach (self::CHILD_ORCHESTRATOR_CODES as $code) {
            if (!Stream::exists($code)) {
                throw new Exception(
                    "Поток «{$code}» не создан после запуска. Проверьте, что flow активен и process_code уникален."
                );
            }
        }

        $meta = Stream::connect(self::META_PROCESS_CODE);
        $meta->setStateValues([
            'batches_total' => $child_count,
            'batches_processed' => 0,
        ]);

        $errors = [];
        $stopped = [];

        while (true) {
            sleep(1);
            $done = 0;
            $errors = [];
            $stopped = [];

            foreach (self::CHILD_ORCHESTRATOR_CODES as $code) {
                if (!Stream::exists($code)) {
                    $done++;
                    continue;
                }

                $s = Stream::connect($code);
                if ($s->isError() || $s->isStopped() || $s->isCompleted()) {
                    $done++;
                }
                if ($s->isError()) {
                    $errors[$code] = $s->getErrorMessage() ?? 'error';
                } elseif ($s->isStopped()) {
                    $stopped[] = $code;
                }
            }

            $meta->setStateValues(['batches_processed' => $done]);

            if ($done >= $child_count) {
                break;
            }
        }

        if ($errors !== [] || $stopped !== []) {
            $message_parts = [];
            if ($errors !== []) {
                $parts = [];
                foreach ($errors as $c => $m) {
                    $parts[] = "{$c}: {$m}";
                }
                $message_parts[] = 'Ошибки: ' . implode('; ', $parts);
            }
            if ($stopped !== []) {
                $message_parts[] = 'Остановлены: ' . implode(', ', $stopped);
            }
            throw new Exception(implode(' | ', $message_parts));
        }
    }
}
