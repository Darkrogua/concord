<?php namespace Zen\Chub\Classes\System;

use Zen\Chub\Classes\Enums\LogType;
use Zen\Chub\Classes\Support\Transformers;
use Exception;
use Throwable;

class LogsApp
{
    private static ?Sqlite $base = null;

    public static function addInfo(array $data, ?string $key = null): void
    {
        self::addLog($data, LogType::INFO->value, $key);
    }

    public static function addDebug(array $data, ?string $key = null): void
    {
        self::addLog($data, LogType::DEBUG->value, $key);
    }

    public static function addError(array $data, ?string $key = null): void
    {
        self::handleError($data);
        self::addLog($data, LogType::ERROR->value, $key);
    }

    public static function addErrorFromThrowable(Throwable $error, string $key, array $context = []): void
    {
        self::addError(array_merge(['error' => $error], $context), $key);
    }

    public static function addAdminActionError(Throwable $error, string $action, array $context = []): void
    {
        self::addErrorFromThrowable($error, "Admin: {$action}", array_merge([
            'action' => $action,
            'user_id' => \Backend\Facades\BackendAuth::getUser()?->id,
            'path' => request()->path(),
        ], $context));
    }

    public static function addLog(array $data, string $type = LogType::INFO->value, ?string $key = null): void
    {
        self::getBase()->query('records')
            ->insert([
                'type' => $type,
                'key' => $key,
                'data' => Transformers::make()->toJson($data),
                'created_at' => now()->toDateTimeString(),
            ]);
    }

    private static function getBase(): Sqlite
    {
        if (self::$base === null) {
            $db_path = storage_path('chub/bases/logs.sqlite');
            self::$base = Sqlite::connect($db_path);
        }

        return self::$base;
    }

    private static function handleError(array &$data)
    {
        if (!isset($data['error']) || !$data['error'] instanceof Throwable) {
            return;
        }

        /** @var \Throwable $error */
        $error = $data['error'];

        $data['error'] = [
            'class' => $error::class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ];
    }
}