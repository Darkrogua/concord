<?php namespace Zen\Chub\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;
use DB;

class BuilderTableCreateZenChubGeoAliases extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_geo_aliases', function ($table) {
            $table->increments('id')->unsigned();
            $table->integer('geo_object_id')->unsigned()->index();
            $table->string('source_code', 64)->index();
            $table->string('source_id', 128);
            $table->string('source_name')->nullable();
            $table->smallInteger('active')->unsigned()->default(1)->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['source_code', 'source_id'], 'zen_chub_geo_aliases_source_unique');
            $table->index(['geo_object_id', 'active'], 'zen_chub_geo_aliases_geo_active_index');
        });
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_geo_aliases CASCADE');
    }
}