<?php namespace Zen\Act\Api;

use Auth;
use Illuminate\Validation\ValidationException;
use Validator;
use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Models\Act;

class Access extends Api
{
    # GET /act.api/Access:get?act_id=&resource_type=&resource_id=
    public function get()
    {
        $act_id = trim((string) (input('act_id') ?? ''));
        $resource_type = trim((string) (input('resource_type') ?? 'act'));
        $resource_id = trim((string) (input('resource_id') ?? $act_id));

        if ($act_id === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметр act_id обязателен');
        }

        if ($resource_type === 'act') {
            $resource_id = $act_id;
        }

        try {
            $access = AccessApp::make();
            $meta = $access->getMeta($act_id);
            $described = $access->describeGrants($act_id, $resource_type, $resource_id);
            $viewer_login = $this->currentUserLogin();

            $payload = [
                'meta' => $meta,
                'grants' => $described['grants'],
                'effective_grants' => $described['effective_grants'],
                'inherited_from' => $described['inherited_from'],
                'resource_type' => $resource_type,
                'resource_id' => $resource_id,
                'can_manage' => $viewer_login !== null
                    && $access->can($viewer_login, $act_id, $resource_type, $resource_id, 'grant', false),
            ];

            if ($resource_type === 'block') {
                $payload['signer_options'] = $access->listSignerOptions($act_id, $resource_id);
            }

            return $this->ok($payload);
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Access:set  { act_id, resource_type, resource_id, grants }
    public function set()
    {
        if ($csrf = $this->requireCsrfOrFail()) {
            return $csrf;
        }

        $viewer_login = $this->currentUserLogin();
        if ($viewer_login === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'act_id' => ['required', 'string'],
                'resource_type' => ['required', 'string', 'in:act,block,item'],
                'resource_id' => ['required', 'string'],
                'grants' => ['required', 'array'],
                'grants.*.login' => ['required', 'string'],
                'grants.*.role' => ['required', 'string', 'in:viewer,editor,signer,steward'],
            ])->validate();

            $act_id = (string) $input['act_id'];
            $resource_type = (string) $input['resource_type'];
            $resource_id = (string) $input['resource_id'];
            if ($resource_type === 'act') {
                $resource_id = $act_id;
            }

            $access = AccessApp::make();
            $access->setGrants(
                $act_id,
                $resource_type,
                $resource_id,
                array_values($input['grants']),
                $viewer_login
            );

            $described = $access->describeGrants($act_id, $resource_type, $resource_id);

            return $this->ok([
                'grants' => $described['grants'],
                'effective_grants' => $described['effective_grants'],
                'inherited_from' => $described['inherited_from'],
            ]);
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

    # GET /act.api/Access:previewAudiences?act_id={uuid}
    public function previewAudiences()
    {
        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $act_id = trim((string) (input('act_id') ?? ''));
        if ($act_id === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметр act_id обязателен');
        }

        $act = Act::find($act_id);
        if (! $act || $act->owner_id === null || (int) $act->owner_id !== (int) $user->id) {
            return $this->fail('FORBIDDEN', 'Доступ только для владельца акта');
        }

        try {
            return $this->ok([
                'audiences' => AccessApp::make()->listPreviewAudiences($act_id),
            ]);
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    private function currentUserLogin(): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $login = trim((string) ($user->username ?? ''));

        return $login !== '' ? $login : null;
    }
}
