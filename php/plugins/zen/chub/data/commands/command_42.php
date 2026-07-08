<?php

\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_features.php');
\Zen\Chub\Console\RefreshCommand::restartMigration('builder_table_create_zen_chub_feature_dependencies.php');

$features_count = \DB::table('zen_chub_features')->count();
$deps_count = \DB::table('zen_chub_feature_dependencies')->count();

return ($features_count || $deps_count) ? 'Произошла ошибка' : 'Миграция успешно перезапущена';