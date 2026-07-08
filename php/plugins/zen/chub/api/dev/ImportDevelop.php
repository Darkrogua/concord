<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\Import\ImportAllData;

class ImportDevelop
{
    # http://axis/chub.api/Dev.ImportDevelop:import
    public function import()
    {
        ImportAllData::make()->handle();
    }
}