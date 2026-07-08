<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\System\BasesApp;

class ComparisonsApp
{
    public static function make(): self
    {
        return new self();
    }

    public function add(string $model_name, int $source_id, int $current_id)
    {
        $record = BasesApp::connect('comparisons')
            ->query()
            ->where([
                'model_name' => $model_name,
                'source_id' => $source_id,
                'current_id' => $current_id,
            ])
            ->first();
        if (!$record) {
            BasesApp::connect('comparisons')
            ->query()
            ->insert([
                'model_name' => $model_name,
                'source_id' => $source_id,
                'current_id' => $current_id,
            ]);
        }
    }

    public function getCurrentId(string $model_name, int $source_id): ?int
    {
        $record = BasesApp::connect('comparisons')
            ->query()
            ->where([
                'model_name' => $model_name,
                'source_id' => $source_id
            ])
            ->first();
        if (!$record) {
            return null;
        }
        return $record->current_id;
    }

    public function getCurrentIds(string $model_name, array $source_ids): array
    {
        $records = BasesApp::connect('comparisons')
            ->query()
            ->where([
                'model_name' => $model_name,
                'source_id' => $source_ids
            ])
            ->get();
        return $records->pluck('current_id', 'source_id')->toArray();
    }

    public function getSourceId(string $model_name, int $current_id): ?int
    {
        $record = BasesApp::connect('comparisons')
            ->query()
            ->where([
                'model_name' => $model_name,
                'current_id' => $current_id
            ])
            ->first();
        if (!$record) {
            return null;
        }
        return $record->source_id;
    }

    public function getSourceIds(string $model_name, array $current_ids): array
    {
        $records = BasesApp::connect('comparisons')
            ->query()
            ->where([
                'model_name' => $model_name,
                'current_id' => $current_ids
            ])
            ->get();
        return $records->pluck('source_id', 'current_id')->toArray();
    }
}