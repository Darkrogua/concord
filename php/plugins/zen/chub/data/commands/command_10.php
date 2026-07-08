<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_geo_objects.php');

return \DB::table('zen_chub_geo_objects')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';