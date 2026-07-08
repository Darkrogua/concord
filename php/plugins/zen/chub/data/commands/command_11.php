<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_geo_aliases.php');

return \DB::table('zen_chub_geo_aliases')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';