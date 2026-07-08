<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_flows.php');

return \DB::table('zen_chub_flows')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';