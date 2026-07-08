<?php namespace Zen\Act\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Пересоздание таблицы с UUID (v1.0.2 создавала integer id).
 * Безопасно при пустой таблице; при наличии данных — сделайте бэкап перед миграцией.
 */
class BuilderTableRecreateZenActActsUuid extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('zen_act_acts');

        Schema::create('zen_act_acts', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('data')->nullable();
            $table->uuid('owner_id')->nullable()->index();
            $table->timestamp('activate_at')->nullable();
            $table->timestamp('stop_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zen_act_acts');
    }
}
