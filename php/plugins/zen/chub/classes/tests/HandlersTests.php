<?php namespace Zen\Chub\Classes\Tests;

use Zen\Chub\Classes\System\BasesApp;

class HandlersTests
{
    public static function make(): self
    {
        return new self();
    }

    public function testHandlerWithData(array $data)
    {
        sleep(1);
        $log_path = base_path('storage/streams_tests.log');
        file_put_contents(
            $log_path,
            'Выполнение задачи: ' . $data['$i'] . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Тестовая функция Zen.Chub.Classes.Tests.HandlersTests.testHandlerWithoutData
     */
    public function testHandlerWithoutData()
    {
        sleep(10);

        $time = now()->toDateTimeString();

        $log_path = base_path('storage/test_handler_without_data.log');
        file_put_contents(
            $log_path,
            "Задача выпонена $time" . PHP_EOL,
            FILE_APPEND
        );
    }

    ## FULL TESTS HANDLER

    # Путь о файла лога
    private function logPath(): string
    {
        return base_path('storage/streams_full_test.log');
    }

    # Метод логирования
    private function testLog(string $text)
    {
        $time = now()->toDateTimeString();
        file_put_contents(
            $this->logPath(),
            $time . ": " . $text . PHP_EOL,
            FILE_APPEND
        );
    }

    # Стартовый хук - Метод
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerStartPathTest
    public function handlerStartPathTest(array $data)
    {
        if (is_file($this->logPath())) {
            unlink($this->logPath());
        }
    
        $data['start_data'] = $data['start_data'] . ' обработаны';
        return $data;
    }

    # Стартовый хук - Источник
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerStartPathSourceTest
    public function handlerStartPathSourceTest(): array
    {
        return ['start_data' => 'Стартовые данные'];
    }

    # Стартовый хук - Цель
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerStartPathTargetTest
    public function handlerStartPathTargetTest(array $data)
    {
        $this->testLog($data['start_data']);
    }

    # Обработчик - Метод
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerPathTest
    public function handlerPathTest(array $batch)
    {
        $i = $batch['i'];
        sleep(1);
        return [
            'result' => "handlerPathTest: Обработан пакет $i"
        ];
    }

    # Обработчик - Источник (ичтоник пакетов (!) возвращает 100 пакетов)
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerPathSourceTest
    public function handlerPathSourceTest(): array
    {
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'i' => $i
            ];
        }
        return $data;
    }

    # Обработчик - Цель
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerPathTargetTest
    public function handlerPathTargetTest(array $data)
    {
        $this->testLog($data['result']);
    }

    # Финишный хук - Метод
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerFinishPathTest
    public function handlerFinishPathTest(array $data)
    {
        $data['finich_data'] = $data['finich_data'] . ' обработаны';
        return $data;
    }

    # Финишный хук - Источник
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerFinishPathSourceTest
    public function handlerFinishPathSourceTest()
    {
        return ['finich_data' => 'Финишируещие данные'];
    }

    # Финишный хук - Цель
    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.handlerFinishPathTargetTest
    public function handlerFinishPathTargetTest(array $data)
    {
        $this->testLog($data['finich_data']);
    }

    # Dotpath: Zen.Chub.Classes.Tests.HandlersTests.pannerTest
    public function pannerTest()
    {
        $time = now()->format('d.m.Y H:i:s');
        BasesApp::connect('testbase')
            ->query()
            ->insert([
                'data' => "$time - Запись добавлена"
            ]);
    }
}