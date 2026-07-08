<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubGeoObjects extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_geo_objects', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->integer('parent_id')->unsigned()->nullable()->index();
            $table->string('object_type', 32)->default('place')->index();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->text('data')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_geo_objects CASCADE');
    }
}