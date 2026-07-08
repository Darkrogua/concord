<?php namespace Zen\Chub\Api;

class Api
{
    /**
     * Проверка CSRF для защищённых методов
     */
    protected function requireCsrf(): void
    {
        $csrf_token = request()->header('X-CSRF-TOKEN')
            ?? request()->header('X-OCTOBER-REQUEST-TOKEN')
            ?? input('_token');
        $csrf_token = $csrf_token !== null ? trim((string) $csrf_token) : null;

        $server_token = csrf_token();
        if ($server_token instanceof \SensitiveParameterValue) {
            $server_token = $server_token->getValue();
        }
        $server_token = $server_token !== null ? (string) $server_token : '';

        if (!$csrf_token || !hash_equals($server_token, $csrf_token)) {
            throw new \Exception('CSRF token mismatch');
        }
    }
}
