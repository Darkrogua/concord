<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\Connectors\MysqlConnection;

class ConnectionsDevelop
{
    # http://axis/chub.api/Dev.ConnectionsDevelop:test
    public function test()
    {
        $azimut74_db = MysqlConnection::make()->azimut74('mcmraak_rivercrs_motorships');

        dd(
            $azimut74_db->count()
        );
    }
}