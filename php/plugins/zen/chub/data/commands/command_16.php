<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_deck_aliases.php');

return \DB::table('zen_chub_deck_aliases')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';