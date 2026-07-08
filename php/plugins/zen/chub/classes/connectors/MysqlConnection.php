<?php namespace Zen\Chub\Classes\Connectors;

use Config;
use DB;
use Illuminate\Database\Query\Builder;

class MysqlConnection
{
    public static function make(): self
    {
        return new self();
    }

    public function azimut74(string $table): Builder
    {
        $host = env('AZIMUT74_IP', 'azimut74-db');
        $port = $host === 'azimut74-db' ? 3306 : 3310;


        Config::set("database.connections.azimut_local", [
            'driver'    => 'mysql',
            'host'      => $host,
            'port'      => $port,
            'database'  => 'azimut',
            'username'  => 'azimut',
            'password'  => 'azimut',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        return DB::connection('azimut_local')->table($table);
    }

    # Подключение к удалённой БД
    public function azimut74vps(string $table): Builder
    {
        Config::set("database.connections.azimut_vps", [
            'driver'    => 'mysql',
            'host'      => '193.168.49.15',
            'port'      => 3310,
            'database'  => 'azimut',
            'username'  => 'azimut',
            'password'  => 'azimut',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        return DB::connection('azimut_vps')->table($table);
    }
}