<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_checkins.php');

return (\DB::table('zen_chub_checkin_route_points')->count() || \DB::table('zen_chub_checkins')->count()) ? 'Произошла ошибка' : 'Миграция успешно перезапущена';