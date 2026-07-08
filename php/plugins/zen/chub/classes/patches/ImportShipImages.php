<?php namespace Zen\Chub\Classes\Patches;

use Zen\Chub\Classes\Import\ImportShipImages as Import;

class ImportShipImages
{
    public function handle()
    {
        Import::make()->handle();
    }
}