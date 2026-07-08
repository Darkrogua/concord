<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_ships.php');

return \DB::table('zen_chub_ships')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';