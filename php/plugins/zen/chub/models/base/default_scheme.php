<?php

$sqlite->createTable('records', function($table) {
    $table->id();
    $table->text('data');
});