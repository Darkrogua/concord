<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubDecks extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_decks', function ($table) {
            $table->increments('id')->unsigned();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->unsignedInteger('root_deck_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('root_deck_id');
        });

        // Системная палуба "Любая" для источников без явной дек-компоненты в цене.
        // Не задаем id вручную, чтобы не ломать auto-increment/sequence на разных СУБД.
        // На новой таблице первая запись получит id=1 автоматически.
        if (!DB::table('zen_chub_decks')->where('name', 'Любая')->exists()) {
            $created_at = date('Y-m-d H:i:s');
            $deck_id = DB::table('zen_chub_decks')->insertGetId([
                'parent_id' => null,
                'root_deck_id' => null,
                'name' => 'Любая',
                'description' => 'Системная палуба для цен без привязки к конкретной палубе',
                'active' => 1,
                'sort_order' => 1,
                'created_at' => $created_at,
                'updated_at' => $created_at,
            ]);

            DB::table('zen_chub_decks')
                ->where('id', $deck_id)
                ->update(['root_deck_id' => $deck_id]);
        }
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_decks CASCADE');
    }
}
