<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubCommands extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_commands', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->smallInteger('is_folder')->unsigned()->default(0);
            $table->smallInteger('count_enabled')->unsigned()->default(0);
            $table->smallInteger('output_log')->unsigned()->default(0);
            $table->smallInteger('active')->unsigned()->default(1);
            $table->smallInteger('api_enabled')->unsigned()->default(0);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_commands CASCADE');
    }
}