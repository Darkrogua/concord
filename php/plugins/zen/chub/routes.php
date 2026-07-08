<?php
/**
 * Динамическая маршрутизация через api-классы
 */

use Zen\Chub\Classes\Support\Transformers;

Route::match(['get', 'post'], '/chub.api/{class}:{method}', function (string $class, string $method) {
    $path = str_replace('.', '\\', $class);
    $response = app("Zen\Chub\Api\\$path")->{$method}();

    # Преобразовать в красивый json
    if (is_array($response)) {
        $response = Transformers::make()->toJson($response, true);
    }

    # Для отладки
    if (request()->has('debug')) {
        return
            '<style>body{background-color:#071125;color:#fff;}</style>' .
            "<pre>$response</pre>";
    }

    return $response;
})->middleware(['web']);