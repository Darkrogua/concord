<?php namespace Zen\Chub\Classes\System;

use Zen\Chub\Classes\Support\Transformers;

class StatesApp
{
    public static function getSetting(string $key): mixed
    {
        $parts = explode('.', $key);
        $code = $parts[0];
        $field = $parts[1];
        $path = base_path("plugins/zen/chub/data/states/state_$code.json");
        $state = Transformers::make()->arrayFromFile($path);
        return $state[$field];
    }
}