<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\CommandApp;
use Zen\Chub\Models\Command as CommandModel;

class CommandCommand extends Command
{
    protected $signature = 'chub:command
        {code : Код команды для выполнения или ai для AI-режима}
        {action? : Подкоманда AI-режима: list, create, update}
        {--data= : JSON-объект для create и update}';
    protected $description = 'Выполнение команд и AI-управление сущностью Command';

    public function handle(): int
    {
        $code = trim((string) $this->argument('code'));

        if ($code !== 'ai') {
            CommandApp::exec($code);

            return self::SUCCESS;
        }

        $action = trim((string) $this->argument('action'));
        if ($action === '') {
            return $this->unknownAiAction($action);
        }

        try {
            return match ($action) {
                'list' => $this->handleAiList(),
                'create' => $this->handleAiCreate(),
                'update' => $this->handleAiUpdate(),
                default => $this->unknownAiAction($action),
            };
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function handleAiList(): int
    {
        $items = CommandModel::query()
            ->where('active', 1)
            ->where('is_folder', 0)
            ->orderBy('id')
            ->get(['id', 'name', 'description'])
            ->map(function (CommandModel $command) {
                $command_id = (int) $command->id;

                return [
                    'id' => $command_id,
                    'name' => (string) ($command->name ?? ''),
                    'description' => (string) ($command->description ?? ''),
                    'file_path' => 'plugins/zen/chub/data/commands/command_' . $command_id . '.php',
                ];
            })
            ->values()
            ->all();

        return $this->emitJson(['items' => $items]);
    }

    private function handleAiCreate(): int
    {
        $data = $this->requireDataOption();
        $data_code = array_key_exists('data', $data) ? $data['data'] : null;
        unset($data['id'], $data['data']);

        /** @var CommandModel $model */
        $model = CommandModel::create($data);

        if ($data_code !== null) {
            $model->data = is_string($data_code) ? $data_code : '';
            $model->save();
        }

        return $this->emitJson([
            'ok' => true,
            'id' => (int) $model->id,
            'code' => (string) ($model->code ?? ''),
        ]);
    }

    private function handleAiUpdate(): int
    {
        $data = $this->requireDataOption();
        $id = $this->extractIdFromData($data);
        $data_code = array_key_exists('data', $data) ? $data['data'] : null;
        unset($data['id'], $data['data']);

        $model = CommandModel::query()->find($id);
        if (!$model) {
            $this->error("Запись id={$id} не найдена.");

            return self::FAILURE;
        }

        $model->fill($data);

        if ($data_code !== null) {
            $model->data = is_string($data_code) ? $data_code : '';
        }

        $model->save();

        return $this->emitJson([
            'ok' => true,
            'id' => (int) $model->id,
            'code' => (string) ($model->code ?? ''),
        ]);
    }

    private function unknownAiAction(string $action): int
    {
        $shown_action = $action !== '' ? $action : '(empty)';
        $this->error("Неизвестная AI-подкоманда \"{$shown_action}\". Используйте: php artisan chub:command ai {list|create|update}");

        return self::FAILURE;
    }

    /**
     * @return array<string,mixed>
     */
    private function requireDataOption(): array
    {
        $raw = (string) $this->option('data');
        $decoded = Transformers::make()->fromJson($raw);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Ожидается валидный JSON-объект в --data=');
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function extractIdFromData(array $data): int
    {
        $raw_id = $data['id'] ?? null;
        if (!is_numeric($raw_id)) {
            throw new \InvalidArgumentException('Для update укажите id в --data: {"id": 10, ...}');
        }

        $id = (int) $raw_id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Для update id должен быть положительным числом.');
        }

        return $id;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function emitJson(array $data): int
    {
        $json = Transformers::make()->toJson($data, true, true);
        $this->line($json ?? '{}');

        return self::SUCCESS;
    }
}