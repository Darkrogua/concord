<?php namespace Zen\Act\Api;

use Auth;
use Illuminate\Validation\ValidationException;
use Validator;
use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Classes\System\BlockApp;
use Zen\Act\Models\Act;

class Blocks extends Api
{
    # GET /act.api/Blocks:list?act_id={uuid}&front_view=1&as_viewer={login|@public|@authenticated}
    public function list()
    {
        $act_id = $this->requireActId();
        if ($act_id === null) {
            return $this->fail('VALIDATION_ERROR', 'Параметр act_id обязателен');
        }

        try {
            [$effective_login, $front_view] = $this->resolveViewerContext($act_id);
            $blocks = BlockApp::make()->listForViewer($act_id, $effective_login, $front_view);

            return $this->ok(['blocks' => $blocks, 'count' => count($blocks)]);
        } catch (\InvalidArgumentException $exception) {
            return $this->fail('VALIDATION_ERROR', $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # GET /act.api/Blocks:show?act_id={uuid}&id={block_id}
    public function show()
    {
        $act_id = $this->requireActId();
        $id = trim((string) (input('id') ?? ''));
        if ($act_id === null || $id === '') {
            return $this->fail('VALIDATION_ERROR', 'Параметры act_id и id обязательны');
        }

        try {
            [$effective_login, $front_view] = $this->resolveViewerContext($act_id);
            $block = BlockApp::make()->showForViewer(
                $act_id,
                $id,
                $effective_login,
                $front_view
            );
            if ($block === null) {
                return $this->fail('NOT_FOUND', 'Блок не найден');
            }

            return $this->ok(['block' => $block]);
        } catch (\InvalidArgumentException $exception) {
            return $this->fail('VALIDATION_ERROR', $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Blocks:create  { act_id, name, data? }
    public function create()
    {
        $this->requireCsrf();
        $user_id = $this->requireUserId();
        if ($user_id === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'act_id' => ['required', 'string'],
                'name' => ['required', 'string', 'max:255'],
            ])->validate();

            $block = BlockApp::make()->create((string) $input['act_id'], $input, $user_id);

            return $this->ok(['block' => $block]);
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

    # POST /act.api/Blocks:update  { act_id, id, name?, data? }
    public function update()
    {
        $this->requireCsrf();
        $user_id = $this->requireUserId();
        if ($user_id === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'act_id' => ['required', 'string'],
                'id' => ['required', 'string'],
            ])->validate();

            $block = BlockApp::make()->update((string) $input['act_id'], (string) $input['id'], $input, $user_id);
            if ($block === null) {
                return $this->fail('NOT_FOUND', 'Блок не найден');
            }

            return $this->ok(['block' => $block]);
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

    # POST /act.api/Blocks:delete  { act_id, id }
    public function delete()
    {
        $this->requireCsrf();
        $user_id = $this->requireUserId();
        if ($user_id === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        $input = $this->requestPayload();
        $act_id = trim((string) ($input['act_id'] ?? ''));
        $id = trim((string) ($input['id'] ?? ''));
        if ($act_id === '' || $id === '') {
            return $this->fail('VALIDATION_ERROR', 'Поля act_id и id обязательны');
        }

        try {
            return $this->ok(BlockApp::make()->delete($act_id, $id, $user_id));
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    # POST /act.api/Blocks:reorder  { act_id, block_ids: string[] }
    public function reorder()
    {
        $this->requireCsrf();
        $user_id = $this->requireUserId();
        if ($user_id === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'act_id' => ['required', 'string'],
                'block_ids' => ['required', 'array'],
                'block_ids.*' => ['required', 'string'],
            ])->validate();

            $result = BlockApp::make()->reorder(
                (string) $input['act_id'],
                array_values($input['block_ids']),
                $user_id
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

    # POST /act.api/Blocks:signChecklistItem  { act_id, id, item_id }
    public function signChecklistItem()
    {
        $this->requireCsrf();
        $user_id = $this->requireUserId();
        if ($user_id === null) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            $input = $this->requestPayload();
            Validator::make($input, [
                'act_id' => ['required', 'string'],
                'id' => ['required', 'string'],
                'item_id' => ['required', 'string'],
            ])->validate();

            $block = BlockApp::make()->signChecklistItem(
                (string) $input['act_id'],
                (string) $input['id'],
                (string) $input['item_id'],
                $user_id
            );
            if ($block === null) {
                return $this->fail('NOT_FOUND', 'Блок не найден');
            }

            return $this->ok(['block' => $block]);
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

    # GET /act.api/Blocks:verify?act_id={uuid}
    public function verify()
    {
        $act_id = $this->requireActId();
        if ($act_id === null) {
            return $this->fail('VALIDATION_ERROR', 'Параметр act_id обязателен');
        }

        try {
            return $this->ok(BlockApp::make()->verify($act_id));
        } catch (\Throwable $exception) {
            return $this->fail('RUNTIME_ERROR', $exception->getMessage());
        }
    }

    private function requireUserId(): ?int
    {
        return $this->currentUserId();
    }

    private function requireActId(): ?string
    {
        $act_id = trim((string) (input('act_id') ?? ''));

        return $act_id !== '' ? $act_id : null;
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

    private function currentUserId(): ?int
    {
        $user = Auth::user();

        return $user ? (int) $user->id : null;
    }

    private function frontViewRequested(): bool
    {
        return filter_var(input('front_view') ?? input('preview') ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function resolveViewerContext(string $act_id): array
    {
        $front_view = $this->frontViewRequested();
        $session_login = $this->currentUserLogin();
        $as_viewer = trim((string) (input('as_viewer') ?? ''));
        $as_viewer = $as_viewer !== '' ? $as_viewer : null;

        $viewer = Auth::user();
        $act = Act::find($act_id);
        $is_mine = $viewer !== null
            && $act !== null
            && $act->owner_id !== null
            && (int) $act->owner_id === (int) $viewer->id;

        $effective_login = AccessApp::make()->resolveViewerLogin(
            $session_login,
            $act_id,
            $front_view,
            $is_mine,
            $as_viewer
        );

        return [$effective_login, $front_view];
    }
}
