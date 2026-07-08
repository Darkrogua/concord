<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\System\Stream;

use Zen\Chub\Classes\Tests\HandlersTests;

class StreamDevelop {

    # http://axis/chub.api/Dev.StreamDevelop:testHandlerWithData
    public function testHandlerWithData()
    {
        // Stream::clearStateStorage();
        // $log_path = base_path('storage/streams_tests.log');

        // # Удаляем тестовый лог
        // if (file_exists($log_path)) {
        //     unlink($log_path);
        // }

        $st = Stream::connect('b3055f5a-8b66-4540-99cb-e0c02c9e6a24');
        //$st->getStateData();

        //dd($st->getStateData());

        $st->clearState();
        dd('clear');

        # Создаём новый поток
        $stream = Stream::create();

        # Добавляем батчи
        for ($i=0; $i<10; $i++) {
            $stream->addBatch([
                '$i' => $i
            ]);
        }

        # Определяем обработчик
        $stream->defineHandler('Zen.Chub.Classes.Tests.HandlersTests.testHandlerWithData');
        $stream_uid = $stream->uid();
        $stream->streamRun();
        return "<a target='_blank' href='http://axis/chub.api/StreamApi:state?debug&uid=$stream_uid'>Ссылка на состояние</a>";
    }

    # http://axis/chub.api/Dev.StreamDevelop:testHandlerWithoutData
    public function testHandlerWithoutData()
    {
        Stream::clearStateStorage();
        $log_path = base_path('storage/test_handler_without_data.log');
        if (file_exists($log_path)) {
            unlink($log_path);
        }

        # Создаём новый поток
        $stream = Stream::create();
        $stream->defineHandler('Zen.Chub.Classes.Tests.HandlersTests.testHandlerWithoutData');
        $stream_uid = $stream->uid();
        $stream->streamRun();
        return "<a target='_blank' href='http://axis/chub.api/StreamApi:state?debug&uid=$stream_uid'>Ссылка на состояние</a>";
    }

    # http://axis/chub.api/Dev.StreamDevelop:fullTest
    public function fullTest()
    {
        # Очистим хранилище потоков
        Stream::clearStateStorage();

        $stream = Stream::create('full.test.stream');

        $dotpath = 'Zen.Chub.Classes.Tests.HandlersTests';

        # Назначаем обработчик стартового хука с источником и целью
        $stream->defineStartHandler(
            "$dotpath.handlerStartPathTest",
            "$dotpath.handlerStartPathSourceTest",
            "$dotpath.handlerStartPathTargetTest"
        );

        # Назначаем обработчик с источником и целью
        $stream->defineHandler(
            "$dotpath.handlerPathTest",
            "$dotpath.handlerPathSourceTest",
            "$dotpath.handlerPathTargetTest"
        );

        # Назначаем обработчик финишного хука с источником и целью
        $stream->defineFinishHandler(
            "$dotpath.handlerFinishPathTest",
            "$dotpath.handlerFinishPathSourceTest",
            "$dotpath.handlerFinishPathTargetTest"
        );

        $stream->streamRun();
        $stream_uid = $stream->uid();

        return "<a target='_blank' href='http://axis/chub.api/StreamApi:state?debug&uid=$stream_uid'>Ссылка на состояние</a>";
    }
}