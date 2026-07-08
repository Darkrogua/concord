<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

# Тут сразу две миграции, заезды и их маршруты

class BuilderTableCreateZenChubCheckins extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_checkins', function($table) {
            $table->increments('id')->unsigned();
            $table->integer('provider_id')->unsigned()->index(); # Идентификатор внешнего источника
            $table->integer('source_id')->unsigned()->index(); # Идентификатор заезда в системе внешнего источника
            $table->integer('ship_id')->unsigned()->index();
            $table->dateTime('date_start')->index();
            $table->dateTime('date_end')->index();
            $table->text('description')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('provider_id')
                ->references('id')
                ->on('zen_chub_providers')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('ship_id')
                ->references('id')
                ->on('zen_chub_ships')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Schema::create('zen_chub_checkin_route_points', function ($table) {
            $table->increments('id')->unsigned();
            $table->integer('checkin_id')->unsigned()->index();
            $table->integer('geo_object_id')->unsigned()->index();
            $table->string('point_type', 32)->index();
            $table->dateTime('arrival_at')->nullable();
            $table->dateTime('departure_at')->nullable();
            $table->smallInteger('is_highlight')->unsigned()->default(0);
            $table->text('note')->nullable();
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['checkin_id', 'sort_order'], 'zen_chub_checkin_route_points_checkin_sort');
            $table->index(['checkin_id', 'point_type'], 'zen_chub_checkin_route_points_checkin_type');
            $table->index(['geo_object_id', 'point_type'], 'zen_chub_checkin_route_points_geo_type');

            $table->foreign('checkin_id')
                ->references('id')
                ->on('zen_chub_checkins')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('geo_object_id')
                ->references('id')
                ->on('zen_chub_geo_objects')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_checkin_route_points CASCADE');
        DB::statement('DROP TABLE IF EXISTS zen_chub_checkins CASCADE');
    }
}