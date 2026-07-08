<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_surcharge_pivot.php');

return \DB::table('zen_chub_ship_surcharge_pivot')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';