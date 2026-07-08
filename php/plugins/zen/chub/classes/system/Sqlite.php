<?php namespace Zen\Chub\Classes\System;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Closure;
use Exception;

/**
 * Это утилитарный класс-обёртка для комфортного управления базами данных SQLite
 */

class Sqlite
{
    private string $db_name;
    private ?string $default_table = null;

    /**
     * Тут в конструкторе передаётся полный путь к базе
     */
    public function __construct(private string $db_path)
    {
        $this->createConnection();
    }

    /**
     * Создать новую базу данных
     */
    public static function create(string $db_path): self
    {
        if (file_exists($db_path)) {
            throw new Exception('Base exists');
        }
        touch($db_path);
        return new self($db_path);
    }

    /**
     * Подключиться к существующей базе данных
     */
    public static function connect(string $db_path): self
    {
        if (!file_exists($db_path)) {
            throw new Exception('Base not exists');
        }
        return new self($db_path);
    }

    /**
     * Получить путь к базе данных
     */
    public function getDbPath(): string
    {
        return $this->db_path;
    }

    public function getConnectionName(): string
    {
        return $this->db_name;
    }

    /**
     * Создать таблицу
     */
    public function createTable(string $table_name, Closure $schema): void
    {
        if ($this->tableExists($table_name)) {
            throw new Exception('Таблица уже существует: ' . $table_name);
        }

        $this->schema()->create($table_name, function (Blueprint $table) use ($schema) {
            $schema($table);
        });
    }

    /**
     * Проверить, существует ли таблица
     */
    public function tableExists(string $table_name): bool
    {
        return $this->schema()->hasTable($table_name);
    }

    /**
     * Получить builder для работы с базой данных
     */
    public function schema(): Builder
    {
        return Schema::connection($this->db_name);
    }

    /**
     * Получить список таблиц
     */
    public function listTables(): array
    {
        $rows = DB::connection($this->db_name)->select(
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
        );

        return array_map(static fn ($row) => $row->name, $rows);
    }

    /**
     * Получить список полей таблицы
     */
    public function listFields(string $table_name, bool $with_meta = false): array
    {
        $rows = DB::connection($this->db_name)->select(
            'pragma table_info(' . $table_name . ')'
        );

        if (!$with_meta) {
            return array_map(static fn ($row) => $row->name, $rows);
        }

        return array_map(static fn ($row) => [
            'name' => $row->name,
            'type' => $row->type,
            'not_null' => (bool) $row->notnull,
            'default' => $row->dflt_value,
            'primary' => (bool) $row->pk,
        ], $rows);
    }

    /**
     * Добавить поля в существующую таблицу
     */
    public function addFields(string $table_name, Closure $schema): void
    {
        $this->schema()->table($table_name, function (Blueprint $table) use ($schema) {
            $schema($table);
        });
    }

    /**
     * Получить Query Builder для таблицы
     */
    public function query(?string $table_name = null): QueryBuilder
    {
        if ($table_name !== null) {
            $this->default_table = $table_name;
            return DB::connection($this->db_name)->table($table_name);
        }

        if ($this->default_table === null) {
            throw new Exception('Не указана таблица для запроса');
        }

        return DB::connection($this->db_name)->table($this->default_table);
    }

    /**
     * Выполнить сырой SQL-запрос
     */
    public function rawQuery(string $sql, array $bindings = [])
    {
        $trimmed = ltrim($sql);

        if (preg_match('/^(select|pragma|with)\b/i', $trimmed)) {
            return DB::connection($this->db_name)->select($sql, $bindings);
        }

        return DB::connection($this->db_name)->statement($sql, $bindings);
    }

    /**
     * Применить схему из строки PHP
     */
    public function applySchema(string $schema): void
    {
        /** @var self $sqlite */
        $sqlite = $this;
        $schema = preg_replace('/^\s*<\?php\b/', '', $schema);
        eval($schema);
    }

    /**
     * Создать типичную таблицу для записей
     */
    public function createRecordsTable(): void
    {
        $this->createTable('records', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('data')->nullable();
        });
    }

    /**
     * Удалить файл базы данных
     */
    public function drop()
    {
        try {
            DB::disconnect($this->db_name);
        } catch (\Throwable $e) {
        }

        try {
            DB::purge($this->db_name);
        } catch (\Throwable $e) {
        }

        $db_path = $this->getDbPath();

        if (file_exists($db_path)) {
            try {
                unlink($db_path);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Создать соединение с базой данных
     */
    private function createConnection(): void
    {
        $this->db_name = md5($this->db_path);

        DB::purge($this->db_name);

        config(['database.connections.' . $this->db_name => [
                'driver' => 'sqlite',
                'database' => $this->db_path,
                'prefix' => '',
            ]
        ]);

        DB::reconnect($this->db_name);
    }
}