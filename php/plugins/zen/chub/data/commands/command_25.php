<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_specifications.php');

return \DB::table('zen_chub_ship_specifications')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';