<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubShipAmenityPivot extends Migration
{
    public function up(): void
    {
        Schema::create('zen_chub_ship_amenity_pivot', function($table)
        {
            $table->integer('ship_id')->unsigned();
            $table->integer('amenity_id')->unsigned();
            $table->integer('sort_order')->nullable();

            $table->primary(['ship_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_ship_amenity_pivot CASCADE');
    }
}