<?php namespace Zen\Act\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * owner_id → bigint (RainLab.User); удаление колонки data.
 */
class BuilderTableAlterZenActActsOwnerAndDropData extends Migration
{
    public function up(): void
    {
        Schema::table('zen_act_acts', function ($table) {
            $table->dropColumn(['data', 'owner_id']);
        });

        Schema::table('zen_act_acts', function ($table) {
            $table->unsignedBigInteger('owner_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('zen_act_acts', function ($table) {
            $table->dropColumn('owner_id');
        });

        Schema::table('zen_act_acts', function ($table) {
            $table->text('data')->nullable();
            $table->uuid('owner_id')->nullable()->index();
        });
    }
}
