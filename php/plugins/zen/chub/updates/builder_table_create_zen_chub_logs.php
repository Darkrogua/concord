<?php namespace Zen\Chub\Updates;

use October\Rain\Database\Updates\Migration;
use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Classes\System\Files;

class BuilderTableCreateZenChubLogs extends Migration
{
    public function up()
    {
        $sqlite = Sqlite::create($this->getDbPath());

        $sqlite->createTable('records', function ($table) {
            $table->id();
            $table->string('type')->default('info');
            $table->string('key')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        ### Особенность OctoberCMS ###
        # для моделей, которые живут на отдельном DB connection и редактируются
        # через стандартный backend FormController, нужно иметь специальную таблицу
        # deferred_bindings в этом же подключении.
        $sqlite->createTable('deferred_bindings', function ($table) {
            $table->increments('id');
            $table->string('master_type');
            $table->string('master_field');
            $table->string('slave_type');
            $table->integer('slave_id');
            $table->string('session_key');
            $table->mediumText('pivot_data')->nullable();
            $table->boolean('is_bind')->default(true);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Sqlite::connect($this->getDbPath())
            ->drop();
    }
    
    private function getDbPath()
    {
        return Files::make()->defineFilePath(
            storage_path('chub/bases/logs.sqlite')
        );
    }
}