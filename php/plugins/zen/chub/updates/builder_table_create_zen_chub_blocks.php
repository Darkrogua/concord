<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubBlocks extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_blocks', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->smallInteger('is_folder')->unsigned()->default(0);
            $table->text('files')->nullable();
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_blocks CASCADE');
    }
}
