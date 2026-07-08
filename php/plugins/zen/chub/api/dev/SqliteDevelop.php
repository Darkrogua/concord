<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\System\Sqlite;

class SqliteDevelop
{
    # http://axis/chub.api/Dev.SqliteDevelop:test
    public function test()
    {
        $db_path = storage_path('test.sqlite');

        if (file_exists($db_path)) {
            $sqlite = Sqlite::connect($db_path);
        } else {
            $sqlite = Sqlite::create($db_path);
        }

        # Тест на создание самой простой таблицы - Пройден
        # $sqlite->createRecordsTable();
        // $sqlite->createTable('users', function($table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->string('email');
        // });

        //dd($sqlite->listTables());

        // $sqlite->addFields('users', function($table) {
        //     $table->string('telegram')->nullable();
        // });

        $sqlite->query('users')->insert([
            'name' => 'Василий Пупкин',
            'email' => 'vasya@mail.ru'
        ]);

        dd(
            $sqlite->query('users')->get()
        );

        dd(
            $sqlite->listFields('users', true)
        );
    }
}