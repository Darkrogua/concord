<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubFlows extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_flows', function($table)
        {
            $table->increments('id')->unsigned();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->smallInteger('is_folder')->unsigned()->default(0);
            $table->string('code');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_flows CASCADE');
    }
}