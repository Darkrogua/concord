<?php namespace Zen\Act\Api;

class Api
{
    /**
     * @return array<string, mixed>
     */
    protected function requestPayload(): array
    {
        return request()->all();
    }

    protected function requireCsrf(): void
    {
        $csrf_token = request()->header('X-CSRF-TOKEN')
            ?? request()->header('X-OCTOBER-REQUEST-TOKEN')
            ?? request()->header('X-XSRF-TOKEN')
            ?? input('_token');
        $csrf_token = $csrf_token !== null ? trim((string) $csrf_token) : null;

        $server_token = csrf_token();
        if ($server_token instanceof \SensitiveParameterValue) {
            $server_token = $server_token->getValue();
        }
        $server_token = $server_token !== null ? (string) $server_token : '';

        if (! $csrf_token || ! hash_equals($server_token, $csrf_token)) {
            throw new \RuntimeException('CSRF token mismatch');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function requireCsrfOrFail(): ?array
    {
        try {
            $this->requireCsrf();
        } catch (\RuntimeException) {
            return $this->fail('CSRF_MISMATCH', 'Сессия устарела. Обновите страницу и попробуйте снова.');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ok(array $data): array
    {
        return [
            'ok' => true,
            'data' => $data,
            'errors' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fail(string $code, string $message): array
    {
        return [
            'ok' => false,
            'data' => null,
            'errors' => [
                ['code' => $code, 'message' => $message],
            ],
        ];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    protected function validationErrors(\Illuminate\Validation\ValidationException $exception): array
    {
        $errors = [];
        foreach ($exception->errors() as $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'code' => 'VALIDATION_ERROR',
                    'message' => (string) $message,
                ];
            }
        }

        return $errors;
    }
}
