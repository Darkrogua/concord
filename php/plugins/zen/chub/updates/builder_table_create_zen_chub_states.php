<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubStates extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_states', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('code');
            $table->integer('parent_id')->unsigned()->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_states CASCADE');
    }
}