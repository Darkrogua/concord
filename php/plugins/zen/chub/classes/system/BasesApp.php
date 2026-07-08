<?php namespace Zen\Chub\Classes\System;

use Zen\Chub\Models\Base;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Exception;

/**
 * BassesApp - Класс для управления сервисом контейнеров с SQLite-базами,фактически просто обёртка. Сам сервис представляет из
 * себя сущность в плагине Zen.Chub c моделью и контроллером которая управляет сервисным классом $/classes/system/Sqlite.php 
 */

class BasesApp
{
    public function __construct(private Base $base) {}

    /**
     * Подключиться к контейнеру с базой данных
     * @param string $code
     * @return self
     */
    public static function connect(string $code): self
    {
        $base = Base::where('code', $code)->first();
        if (!$base) {
            throw new Exception('База данных не обнаружена: ' . $code);
        }

        return new self($base);
    }

    /**
     * Добавить запись в базу данных
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        return $this->base->addRecord($data);
    }

    /**
     * Получить query builder для базы данных
     * @return QueryBuilder
     */
    public function query(string $table = 'records'): QueryBuilder
    {
        return $this->base->sqliteQuery($table);
    }

    /**
     * Выполнить сырой SQL-запрос в базе
     * @param string $sql
     * @param array<int,mixed> $bindings
     * @return mixed
     */
    public function rawQuery(string $sql, array $bindings = []): mixed
    {
        $sqlite = Sqlite::connect($this->base->getBasePath());

        return $sqlite->rawQuery($sql, $bindings);
    }

    /**
     * Очистить базу данных
     */
    public function clear()
    {
        $this->base->clearBase();
    }
}