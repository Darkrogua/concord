<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubFeatures extends Migration
{
    public function up(): void
    {
        Schema::create('zen_chub_features', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('section_heading')->nullable();
            $table->text('description')->nullable();
            $table->string('feature_group')->nullable();
            $table->text('tags')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->text('acceptance_checks')->nullable();
            $table->string('workflow_status', 32)->default('');
            $table->text('workflow_comment')->nullable();
            $table->text('workflow_files')->nullable();
            $table->text('workflow_tags')->nullable();
            $table->smallInteger('active')->unsigned()->default(1);
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('zen_chub_features', function ($table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('zen_chub_features')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_feature_dependencies CASCADE');
        DB::statement('DROP TABLE IF EXISTS zen_chub_features CASCADE');
    }
}
