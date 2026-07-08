<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubCabins extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_cabins', function ($table) {
            $table->increments('id')->unsigned();
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            $table->integer('deck_id')->unsigned();
            $table->integer('grade_id')->unsigned();

            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_cabins CASCADE');
    }
}