<?php namespace Zen\Chub\Updates;

use DB;
use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateZenChubDocuments extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('zen_chub_documents')) {
            Schema::create('zen_chub_documents', function ($table) {
                $table->increments('id')->unsigned();
                $table->string('name')->nullable();
                $table->string('code')->nullable()->index();
                $table->text('props')->nullable();
                $table->integer('parent_id')->unsigned()->nullable()->index();
                $table->smallInteger('is_folder')->unsigned()->default(0);
                $table->smallInteger('active')->unsigned()->default(1);
                $table->integer('sort_order')->unsigned()->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        } else {
            Schema::table('zen_chub_documents', function ($table) {
                if (!Schema::hasColumn('zen_chub_documents', 'code')) {
                    $table->string('code')->nullable()->index();
                }

                if (!Schema::hasColumn('zen_chub_documents', 'parent_id')) {
                    $table->integer('parent_id')->unsigned()->nullable()->index();
                }

                if (!Schema::hasColumn('zen_chub_documents', 'is_folder')) {
                    $table->smallInteger('is_folder')->unsigned()->default(0);
                }
            });
        }

        DB::statement("
            UPDATE zen_chub_documents
            SET code = concat('document_', id)
            WHERE code IS NULL OR trim(code) = ''
        ");
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS zen_chub_documents CASCADE');
    }
}