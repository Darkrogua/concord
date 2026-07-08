<?php namespace Zen\Chub\Api;

use Zen\Chub\Classes\Support\Transformers;

/**
 * Класс для отладки через url
 */
class Debug
{
    # http://axis/chub.api/Debug:test
    public function test()
    {
        $transformers = Transformers::make();

        $array = [
            'test' => 'test',
            'test2' => 'test2/test',
            'test3' => 'test3',
        ];

        $json = $transformers->toJson($array, true, true);

        return $json;
    }

    # http://axis/chub.api/Debug:apiTest
    public function apiTest()
    {
        return [
            'text' => 'Данные с бекенда'
        ];
    }
}