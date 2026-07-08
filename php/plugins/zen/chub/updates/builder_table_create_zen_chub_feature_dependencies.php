<?php namespace Zen\Chub\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;
use DB;

class BuilderTableCreateZenChubFeatureDependencies extends Migration
{
    public function up(): void
    {
        Schema::create('zen_chub_feature_dependencies', function ($table) {
            $table->uuid('feature_id');
            $table->uuid('depends_on_feature_id');
            $table->text('comment')->nullable();

            $table->primary(['feature_id', 'depends_on_feature_id'], 'zen_chub_feature_deps_pk');

            $table->foreign('feature_id')
                ->references('id')
                ->on('zen_chub_features')
                ->cascadeOnDelete();

            $table->foreign('depends_on_feature_id')
                ->references('id')
                ->on('zen_chub_features')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_feature_dependencies CASCADE');
    }
}
