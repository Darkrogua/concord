<?php

use Illuminate\Support\Facades\DB;

DB::unprepared(<<<'SQL'
DO $$
DECLARE
    seq_name TEXT;
    tbl_name TEXT;
    col_name TEXT;
    max_id BIGINT;
BEGIN
    FOR seq_name, tbl_name, col_name IN
        SELECT
            sequence_name,
            table_name,
            column_name
        FROM information_schema.sequences
        JOIN information_schema.columns
            ON sequence_name = table_name || '_' || column_name || '_seq'
        WHERE table_schema = 'public'
    LOOP
        EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I', col_name, tbl_name)
        INTO max_id;

        IF max_id = 0 THEN
            EXECUTE format('SELECT setval(%L, 1, false)', seq_name);
        ELSE
            EXECUTE format('SELECT setval(%L, %s)', seq_name, max_id);
        END IF;
    END LOOP;
END $$;
SQL);

return "База данных исправлена";