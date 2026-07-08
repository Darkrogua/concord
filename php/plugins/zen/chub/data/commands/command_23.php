<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_surcharge.php');

return \DB::table('zen_chub_ship_surcharge')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';