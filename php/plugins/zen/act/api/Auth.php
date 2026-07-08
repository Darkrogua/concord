<?php namespace Zen\Act\Api;

use Illuminate\Validation\ValidationException;
use Zen\Act\Classes\System\AuthApp;

class Auth extends Api
{
    # POST /act.api/Auth:login  { login, password, remember? }
    public function login()
    {
        if ($csrfError = $this->requireCsrfOrFail()) {
            return $csrfError;
        }

        try {
            return $this->ok(AuthApp::make()->login($this->requestPayload()));
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'data' => null,
                'errors' => $this->validationErrors($exception),
            ];
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Auth:register  { login, password, password_confirmation?, remember? }
    public function register()
    {
        if ($csrfError = $this->requireCsrfOrFail()) {
            return $csrfError;
        }

        try {
            return $this->ok(AuthApp::make()->register($this->requestPayload()));
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'data' => null,
                'errors' => $this->validationErrors($exception),
            ];
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Auth:logout
    public function logout()
    {
        if ($csrfError = $this->requireCsrfOrFail()) {
            return $csrfError;
        }

        AuthApp::make()->logout();

        return $this->ok(['logged_out' => true]);
    }

    # GET /act.api/Auth:me
    public function me()
    {
        $user = AuthApp::make()->user();
        if ($user === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        return $this->ok(['user' => $user]);
    }

    # GET /act.api/Auth:profile?login=
    public function profile()
    {
        $login = trim((string) (input('login') ?? ''));
        if ($login === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметр login обязателен');
        }

        $payload = AuthApp::make()->profileByLogin($login);
        if ($payload === null) {
            return $this->fail('NOT_FOUND', 'Пользователь не найден');
        }

        return $this->ok($payload);
    }

    # GET /act.api/Auth:searchUsers?q=
    public function searchUsers()
    {
        if (! \Auth::user()) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $query = trim((string) (input('q') ?? input('query') ?? ''));
        $limit = (int) (input('limit') ?? 10);

        return $this->ok([
            'users' => AuthApp::make()->searchUsers($query, $limit),
        ]);
    }

    # POST /act.api/Auth:updateProfile  { name?, email?, timezone? }
    public function updateProfile()
    {
        if ($csrfError = $this->requireCsrfOrFail()) {
            return $csrfError;
        }

        try {
            return $this->ok(AuthApp::make()->updateProfile($this->requestPayload()));
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'data' => null,
                'errors' => $this->validationErrors($exception),
            ];
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Auth:changePassword  { current_password, password, password_confirmation? }
    public function changePassword()
    {
        if ($csrfError = $this->requireCsrfOrFail()) {
            return $csrfError;
        }

        try {
            return $this->ok(AuthApp::make()->changePassword($this->requestPayload()));
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'data' => null,
                'errors' => $this->validationErrors($exception),
            ];
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }
}
