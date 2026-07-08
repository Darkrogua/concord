<?php namespace Zen\Act\Api;

use Auth;
use Illuminate\Validation\ValidationException;
use Zen\Act\Classes\System\ActApp;
use Validator;

class Acts extends Api
{
    # GET /act.api/Acts:list?cursor=&owner_logins=&created_from=&created_to=&sort_created=
    public function list()
    {
        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $params = [
            'cursor' => trim((string) (input('cursor') ?? '')),
            'owner_logins' => input('owner_logins'),
            'created_from' => input('created_from'),
            'created_to' => input('created_to'),
            'sort_created' => input('sort_created'),
            'tags' => input('tags'),
            'tag_ops' => input('tag_ops'),
        ];

        $validator = Validator::make($params, [
            'cursor' => ['nullable', 'string', 'max:512'],
            'owner_logins' => ['nullable', 'string', 'max:2000'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'sort_created' => ['nullable', 'in:asc,desc'],
            'tags' => ['nullable', 'string', 'max:2000'],
            'tag_ops' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'data' => null,
                'errors' => array_map(
                    fn (string $message): array => ['code' => 'VALIDATION_ERROR', 'message' => $message],
                    $validator->errors()->all()
                ),
            ];
        }

        try {
            $result = ActApp::make()->listForUserPaginated((int) $user->id, $params);
        } catch (\InvalidArgumentException $exception) {
            return $this->fail('VALIDATION_ERROR', $exception->getMessage());
        }

        return $this->ok($result);
    }

    # POST /act.api/Acts:create  { name }
    public function create()
    {
        $this->requireCsrf();

        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();

            $act = ActApp::make()->createForOwner((int) $user->id, $input);

            return $this->ok(['act' => ActApp::make()->actCard($act)]);
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

    # POST /act.api/Acts:update  { id, name }
    public function update()
    {
        $this->requireCsrf();

        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'id' => ['required', 'string'],
                'name' => ['required', 'string', 'max:255'],
            ])->validate();

            $act = ActApp::make()->updateForOwner((int) $user->id, (string) $input['id'], $input);
            if ($act === null) {
                return $this->fail('NOT_FOUND', 'Акт не найден');
            }

            return $this->ok(['act' => ActApp::make()->actCard($act)]);
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

    # GET /act.api/Acts:show?id={uuid}&front_view=1&as_viewer={login|@public|@authenticated}
    public function show()
    {
        $id = trim((string) (input('id') ?? ''));
        if ($id === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметр id обязателен');
        }

        $viewer = Auth::user();
        $viewer_id = $viewer ? (int) $viewer->id : null;
        $front_view = filter_var(input('front_view') ?? input('preview') ?? false, FILTER_VALIDATE_BOOLEAN);
        $as_viewer = trim((string) (input('as_viewer') ?? ''));
        $as_viewer = $as_viewer !== '' ? $as_viewer : null;

        try {
            $payload = ActApp::make()->showForViewer($id, $viewer_id, $front_view, $as_viewer);
        } catch (\InvalidArgumentException $exception) {
            return $this->fail('VALIDATION_ERROR', $exception->getMessage());
        }

        if ($payload === null) {
            return $this->fail('NOT_FOUND', 'Акт не найден');
        }

        return $this->ok($payload);
    }

    # GET /act.api/Acts:listStates?id={uuid}
    public function listStates()
    {
        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $id = trim((string) (input('id') ?? ''));
        if ($id === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметр id обязателен');
        }

        try {
            $snapshots = ActApp::make()->listStatesForOwner((int) $user->id, $id);

            return $this->ok([
                'snapshots' => $snapshots,
                'count' => count($snapshots),
            ]);
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Acts:restore  { id, snapshot_key }
    public function restore()
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
                'id' => ['required', 'string'],
                'snapshot_key' => ['required', 'string'],
            ])->validate();

            $result = ActApp::make()->restoreForOwner(
                (int) $user->id,
                (string) $input['id'],
                (string) $input['snapshot_key']
            );

            return $this->ok($result);
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

    # POST /act.api/Acts:mergeStates  { id, from_index, to_index }
    public function mergeStates()
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
                'id' => ['required', 'string'],
                'from_index' => ['required', 'integer', 'min:1'],
                'to_index' => ['required', 'integer', 'min:1'],
            ])->validate();

            $result = ActApp::make()->mergeStatesForOwner(
                (int) $user->id,
                (string) $input['id'],
                (int) $input['from_index'],
                (int) $input['to_index']
            );

            return $this->ok($result);
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
