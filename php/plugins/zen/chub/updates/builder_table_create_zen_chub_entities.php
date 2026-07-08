<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubEntities extends Migration
{
    public function up(): void
    {
        Schema::create('zen_chub_entities', function($table)
        {
            $table->increments('id')->unsigned();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->string('code');
            $table->string('name');
            $table->smallInteger('active')->unsigned()->default(1);
            $table->string('url')->nullable();
            $table->string('icon_path')->nullable();
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('template_data')->nullable();
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_entities CASCADE');
    }
}