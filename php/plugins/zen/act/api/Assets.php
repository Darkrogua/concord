<?php namespace Zen\Act\Api;

use Auth;
use Validator;
use Zen\Act\Classes\Support\ActAssets;

class Assets extends Api
{
    # POST /act.api/Assets:upload  multipart: act_id, block_id, file
    public function upload()
    {
        $this->requireCsrf();
        $user = Auth::user();
        if (! $user) {
            return $this->fail('UNAUTHORIZED', 'Требуется авторизация');
        }

        try {
            Validator::make(request()->all(), [
                'act_id' => ['required', 'string'],
                'block_id' => ['required', 'string'],
                'file' => ['required', 'file'],
            ])->validate();

            $file = request()->file('file');
            if (! $file) {
                return $this->fail('VALIDATION_ERROR', 'Файл не передан');
            }

            $item = ActAssets::make()->upload(
                trim((string) request('act_id')),
                trim((string) request('block_id')),
                $file,
                (int) $user->id
            );

            return $this->ok(['item' => $item]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
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
}
