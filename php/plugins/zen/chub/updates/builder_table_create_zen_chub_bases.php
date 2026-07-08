<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubBases extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_bases', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('schema')->nullable();
            $table->string('preview_table')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_bases CASCADE');
    }
}