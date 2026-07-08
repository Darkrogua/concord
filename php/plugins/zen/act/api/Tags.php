<?php namespace Zen\Act\Api;

use Auth;
use Illuminate\Validation\ValidationException;
use Validator;
use Zen\Act\Classes\System\TagsApp;

class Tags extends Api
{
    # GET /act.api/Tags:list?act_id={uuid}
    public function list()
    {
        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $act_id = trim((string) (input('act_id') ?? ''));
        if ($act_id === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметр act_id обязателен');
        }

        try {
            $login = $this->currentUserLogin();
            $tags = TagsApp::make()->listForUser($act_id, (string) $login);

            return $this->ok(['tags' => $tags, 'count' => count($tags)]);
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Tags:set  { act_id, tags: string[] }
    public function set()
    {
        if ($csrf = $this->requireCsrfOrFail()) {
            return $csrf;
        }

        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'act_id' => ['required', 'string'],
                'tags' => ['required', 'array'],
                'tags.*' => ['string', 'max:30'],
            ])->validate();

            $tags = TagsApp::make()->setForUser(
                (string) $input['act_id'],
                (string) $this->currentUserLogin(),
                array_values($input['tags'])
            );

            return $this->ok(['tags' => $tags, 'count' => count($tags)]);
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'data' => null,
                'errors' => $this->validationErrors($exception),
            ];
        } catch (\InvalidArgumentException $exception) {
            return $this->fail('VALIDATION_ERROR', $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # GET /act.api/Tags:catalog?q=
    public function catalog()
    {
        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $query = trim((string) (input('q') ?? ''));

        try {
            $tags = TagsApp::make()->catalog((string) $this->currentUserLogin(), $query);

            return $this->ok(['tags' => $tags, 'count' => count($tags)]);
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    private function currentUserLogin(): string
    {
        $user = Auth::user();
        $login = trim((string) ($user->username ?? ''));

        return $login !== '' ? $login : '';
    }
}
