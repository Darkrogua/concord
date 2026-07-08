<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubShipInclusionPivot extends Migration
{
    public function up(): void
    {
        Schema::create('zen_chub_ship_inclusion_pivot', function($table)
        {
            $table->integer('ship_id')->unsigned();
            $table->integer('inclusion_id')->unsigned();
            $table->integer('sort_order')->nullable();

            $table->primary(['ship_id', 'inclusion_id']);
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_ship_inclusion_pivot CASCADE');
    }
}