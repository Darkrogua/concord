<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubShips extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_ships', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->integer('ship_class_id')->unsigned();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_ships CASCADE');
    }
}