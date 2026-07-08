<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_inclusions.php');

return \DB::table('zen_chub_inclusions')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';