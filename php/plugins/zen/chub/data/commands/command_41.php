<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_blocks.php');

return \DB::table('zen_chub_blocks')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';