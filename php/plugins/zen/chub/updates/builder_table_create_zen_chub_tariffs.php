<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubTariffs extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_tariffs', function($table)
        {
            $table->increments('id')->unsigned();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $this->seedDefaultTariffs();
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_tariffs CASCADE');
    }

    protected function seedDefaultTariffs(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        DB::table('zen_chub_tariffs')->insert([
            [
                'name' => 'Базовый',
                'description' => null,
                'active' => 1,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Расширенный',
                'description' => null,
                'active' => 1,
                'sort_order' => 2,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }
}