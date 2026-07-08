<?php

file_put_contents(
    storage_path('test_commands.log'),
    'Run:' . now()->format('d.m.Y H:i:s') . PHP_EOL,
    FILE_APPEND
);

return 'okay';