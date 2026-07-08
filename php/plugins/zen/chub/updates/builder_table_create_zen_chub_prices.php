<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubPrices extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_prices', function($table)
        {
            $table->integer('provider_id')->unsigned();
            $table->integer('checkin_id')->unsigned();
            $table->integer('grade_id')->unsigned();
            $table->integer('deck_id')->unsigned();
            $table->integer('tariff_id')->unsigned();
            $table->integer('places_value')->unsigned()->default(1);
            $table->decimal('price_value', 10, 2);
            $table->string('price_currency', 3);
            $table->timestamp('created_at');

            $table->index('provider_id');
            $table->index('checkin_id');
            $table->index('grade_id');
            $table->index('deck_id');
            $table->index('tariff_id');

            $table->foreign('provider_id')
                ->references('id')
                ->on('zen_chub_providers')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('checkin_id')
                ->references('id')
                ->on('zen_chub_checkins')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            
            $table->foreign('grade_id')
                ->references('id')
                ->on('zen_chub_grades')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            
            $table->foreign('deck_id')
                ->references('id')
                ->on('zen_chub_decks')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            
            $table->foreign('tariff_id')
                ->references('id')
                ->on('zen_chub_tariffs')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique([
                    'checkin_id',
                    'grade_id',
                    'deck_id',
                    'tariff_id',
                    'places_value',
                    'price_currency'
                ], 'zen_chub_prices_unique');
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_prices CASCADE');
    }
}