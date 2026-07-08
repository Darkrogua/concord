<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_providers.php');

return \DB::table('zen_chub_providers')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';