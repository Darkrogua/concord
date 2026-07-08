<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_documents.php');

return \DB::table('zen_chub_documents')->count() ? 'Произошла ошибка' : 'Миграция успешно перезапущена';