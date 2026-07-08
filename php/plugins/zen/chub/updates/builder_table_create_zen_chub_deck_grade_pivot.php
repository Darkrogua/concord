<?php namespace Zen\Chub\Updates;

use Schema;
use DB;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubDeckGradePivot extends Migration
{
    public function up()
    {
        Schema::create('zen_chub_deck_grade_pivot', function($table)
        {
            $table->integer('deck_id')->unsigned();
            $table->integer('grade_id')->unsigned();
        });
    }
    
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_deck_grade_pivot CASCADE');
    }
}
