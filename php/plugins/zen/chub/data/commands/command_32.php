<?php

if (!\Schema::hasTable("zen_chub_bases")) {
    return "Таблица zen_chub_bases не найдена";
}

if (\Schema::hasColumn("zen_chub_bases", "preview_table")) {
    return "Поле preview_table уже существует";
}

\Schema::table("zen_chub_bases", function ($table) {
    $table->string("preview_table")->nullable();
});

return "Поле preview_table успешно добавлено";