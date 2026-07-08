<?php

\Zen\Chub\Console\RefreshCommand::restartMigration("builder_table_create_zen_chub_tariffs.php");

return \DB::table("zen_chub_tariffs")->count() >= 2 ? "Миграция успешно перезапущена" : "Произошла ошибка";