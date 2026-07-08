<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_cabins.php');

return \DB::table('zen_chub_cabins')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';