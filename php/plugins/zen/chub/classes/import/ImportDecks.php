<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Console\RefreshCommand;
use Zen\Chub\Models\Deck;

class ImportDecks
{
    public static function make(): self
    {
        return new self();
    }

    # dp: Zen.Chub.Classes.Import.ImportDecks.handle
    public function handle(): void
    {
        RefreshCommand::restartMigration('builder_table_create_zen_chub_decks.php');

        $records = MysqlConnection::make()->azimut74('mcmraak_rivercrs_decks')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $parent_id = $record->parent_id
                ? ComparisonsApp::make()->getCurrentId('Deck', $record->parent_id)
                : null;

            $current_id = Deck::create([
                'name' => $record->name,
                'sort_order' => $record->sort_order ?? 0,
                'parent_id' => $parent_id,
            ])->id;

            ComparisonsApp::make()->add(
                model_name: 'Deck',
                source_id: $record->id,
                current_id: $current_id
            );
        }
    }
}
