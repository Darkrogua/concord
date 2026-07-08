<?php namespace Zen\Chub\Classes\Patches;

use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Sqlite;

class Patch170220261527
{
    # Dotpath: Zen.Chub.Classes.Patches.Patch170220261527.handle
    public function handle()
    {
        $base_path = storage_path('chub/bases/uon-webhook.sqlite');
        $sqlite = Sqlite::connect($base_path);
        $fields = $sqlite->listFields('records');

        if (!in_array('id_internal', $fields)) {
            $sqlite->addFields('records', function($table) {
                $table->text('id_internal')->nullable();
            });
        }

        $records = $sqlite->query('records')->get();
        foreach ($records as $record) {
            if (!$record->id_internal) {
                $sqlite->query('records')
                    ->where('id', $record->id)
                    ->update([
                        'id_internal' => Transformers::make()->fromJson($record->data)['request']['r_id_internal']
                    ]);
            }
        }
    }
}