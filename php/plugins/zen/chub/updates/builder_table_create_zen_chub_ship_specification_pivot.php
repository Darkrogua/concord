<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubShipSpecificationPivot extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_ship_specification_pivot', function($table)
        {
            $table->integer('ship_id')->unsigned();
            $table->integer('specification_id')->unsigned();
            $table->string('value')->nullable();
            $table->integer('sort_order')->nullable();

            $table->primary(['ship_id', 'specification_id']);
        });
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_ship_specification_pivot CASCADE');
    }
}