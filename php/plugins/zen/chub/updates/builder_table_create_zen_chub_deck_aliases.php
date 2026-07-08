<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubDeckAliases extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_deck_aliases', function($table)
        {
            $table->increments('id')->unsigned();
            $table->text('description')->nullable();
            $table->integer('provider_id')->unsigned();
            $table->integer('target_id'); # Идентификатор местной записи
            $table->string('source_name')->nullable(); # Имя источника
            $table->string('source_uid'); # Идентификатор источника
            $table->text('data')->nullable(); # Метаданные

            $table->smallInteger('active')->unsigned()->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('target_id');
            $table->index('provider_id');

            $table->foreign('target_id')
                ->references('id')
                ->on('zen_chub_decks')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            
            $table->foreign('provider_id')
                ->references('id')
                ->on('zen_chub_providers')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_deck_aliases CASCADE');
    }
}