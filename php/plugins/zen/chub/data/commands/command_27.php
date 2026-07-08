<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_deck_grade_pivot.php');

return \DB::table('zen_chub_deck_grade_pivot')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';