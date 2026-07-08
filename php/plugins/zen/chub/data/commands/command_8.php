<?php
/**
 * Команда Chub (id=8): деплой через тот же сценарий, что и виджет Dashboard.
 *
 * Вызов из админки: Zen\Chub\Controllers\Commands::onRunCommandPopup()
 *   → CommandApp::execModel()
 *   → include этого файла (доступны $input_data).
 *
 * Результат return — строка: попап «Результат выполнения» (formatCommandResult).
 * Лог в SQLite — если у команды count_enabled=1 (см. CommandApp::writeCommandLog).
 */
use Zen\Chub\Controllers\Deploy;

$result = Deploy::runDeployScript();

return ($result['success'] ? 'OK: ' : 'ERR: ') . $result['message'];