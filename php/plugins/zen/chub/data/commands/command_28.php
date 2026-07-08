<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_cabin_equipments.php');

return \DB::table('zen_chub_cabin_equipments')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';