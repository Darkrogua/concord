<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubShipSurchargePivot extends Migration
{
    public function up(): void
    {
        Schema::create('zen_chub_ship_surcharge_pivot', function($table)
        {
            $table->integer('ship_id')->unsigned();
            $table->integer('surcharge_id')->unsigned();
            $table->integer('sort_order')->nullable();

            $table->primary(['ship_id', 'surcharge_id']);
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_ship_surcharge_pivot CASCADE');
    }
}