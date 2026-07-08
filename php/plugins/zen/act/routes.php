<?php
/**
 * Динамическая маршрутизация через api-классы
 */

use Zen\Act\Classes\Support\Transformers;
use Zen\Act\Controllers\AppController;
use Zen\Act\Controllers\AssetsController;

Route::get('/app', [AppController::class, 'index'])->middleware(['web']);
Route::get('/app/{any}', [AppController::class, 'index'])
    ->where('any', '.*')
    ->middleware(['web']);

Route::get('/act_{uuid}', [AppController::class, 'index'])
    ->where('uuid', '[0-9a-fA-F-]{36}')
    ->middleware(['web']);

Route::get('/act.assets/{act_id}/{block_id}/{image_id}', [AssetsController::class, 'show'])
    ->where([
        'act_id' => '[0-9a-fA-F-]{36}',
        'block_id' => '[0-9a-fA-F-]{36}',
        'image_id' => '[0-9a-fA-F-]{36}',
    ])
    ->middleware(['web']);

Route::match(['get', 'post'], '/act.api/{class}:{method}', function (string $class, string $method) {
    $path = str_replace('.', '\\', $class);
    $response = app("Zen\Act\Api\\$path")->{$method}();

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
