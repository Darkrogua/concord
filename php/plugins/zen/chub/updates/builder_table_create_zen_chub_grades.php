<?php namespace Zen\Chub\Updates;

use DB;
use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubGrades extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_grades', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('ship_id')->unsigned();

            $table->integer('places_main_qnt')->default(0); # Счётчик основных мест
            $table->integer('places_extra_qnt')->default(0); # Счётчик дополнительных мест
            $table->integer('rooms_count')->default(0); # Счётчик комнат
            $table->integer('area_meters')->default(0); # Общая площадь каюты в квадратных метрах
            
            $table->string('tiering')->nullable(); # Тип спального места: Неярусное (flat) или Ярусное (tiered)

            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('ship_id');
            $table->foreign('ship_id')
                ->references('id')
                ->on('zen_chub_ships')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_grades CASCADE');
    }
}