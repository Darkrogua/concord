<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_ship_aliases.php');

return \DB::table('zen_chub_ship_aliases')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';