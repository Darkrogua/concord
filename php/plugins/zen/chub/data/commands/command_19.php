<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_amenities.php');

return \DB::table('zen_chub_amenities')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';