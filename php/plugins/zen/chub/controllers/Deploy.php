<?php namespace Zen\Chub\Controllers;

use Backend;
use Backend\Classes\Controller;
use BackendAuth;
use Flash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Zen\Chub\Classes\Support\SupportRocketBot;

/**
 * Контроллер для выполнения деплоя с Dashboard.
 * Общая логика деплоя: {@see runDeployScript()} — её вызывают виджет, `chub:deploy` (`make deploy`) и Chub-команда.
 * Путь к shell-скрипту: env DEPLOY_SCRIPT или base_path('../scripts/deploy.sh').
 */
class Deploy extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return Backend::redirect('dashboard');
    }

    public function onDeploy()
    {
        if (!BackendAuth::getUser()) {
            Flash::error('Доступ запрещён');
            return;
        }

        $result = self::runDeployScript();

        if ($result['success']) {
            Flash::success($result['message']);
        } else {
            Flash::error($result['message']);
        }
    }

    /**
     * Запуск deploy.sh без Flash и без проверки BackendAuth.
     *
     * Используется из Chub-команд (`data/commands/command_*.php`), вызываемых из
     * `Commands::onRunCommandPopup` — там пользователь уже в сессии бэкенда; ограничение
     * прав — через доступ к сущности «Команды» и кнопке запуска, а не через этот метод.
     *
     * @return array{success: bool, message: string}
     */
    public static function runDeployScript(): array
    {
        $deploy_script = env('DEPLOY_SCRIPT') ?: base_path('../scripts/deploy.sh');
        $project_root = dirname(dirname($deploy_script));

        if (!file_exists($deploy_script)) {
            return [
                'success' => false,
                'message' => 'Скрипт деплоя не найден. Укажите DEPLOY_SCRIPT в .env или смонтируйте проект.',
            ];
        }

        try {
            $process = new Process(
                ['sh', $deploy_script],
                $project_root,
                null,
                null,
                360
            );
            $process->run();

            if ($process->isSuccessful()) {
                $combined_out = trim($process->getOutput() . "\n" . $process->getErrorOutput());
                $meta = self::parseDeployScriptMeta($combined_out);
                try {
                    SupportRocketBot::make()->send(self::buildDeployRocketMessage($meta));
                } catch (\Throwable $e) {
                    Log::warning('Deploy: уведомление в Rocket.Chat не отправлено: ' . $e->getMessage());
                }

                return [
                    'success' => true,
                    'message' => self::buildDeployFlashMessage($meta),
                ];
            }

            $output = trim($process->getErrorOutput() ?: $process->getOutput());

            return [
                'success' => false,
                'message' => 'Ошибка деплоя: ' . ($output ?: 'код ' . $process->getExitCode()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Ошибка при выполнении деплоя: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Удалённая prod-сборка Vite: scripts/vite-prod-build.sh (SSH на production).
     * Тот же корень проекта и .env, что и у runDeployScript().
     *
     * @return array{success: bool, message: string} message — объединённый stdout+stderr процесса
     */
    public static function runViteProdBuildScript(): array
    {
        $deploy_script = env('DEPLOY_SCRIPT') ?: base_path('../scripts/deploy.sh');
        $project_root = dirname(dirname($deploy_script));
        $vite_script = $project_root . '/scripts/vite-prod-build.sh';

        if (!file_exists($vite_script)) {
            return [
                'success' => false,
                'message' => 'Скрипт vite-prod-build.sh не найден: ' . $vite_script,
            ];
        }

        try {
            $process = new Process(
                ['sh', $vite_script],
                $project_root,
                null,
                null,
                300
            );
            $process->run();

            $combined = trim($process->getOutput() . "\n" . $process->getErrorOutput());

            if ($process->isSuccessful()) {
                return [
                    'success' => true,
                    'message' => $combined !== '' ? $combined : '(успех, вывод пуст)',
                ];
            }

            return [
                'success' => false,
                'message' => trim(
                    'Код выхода ' . $process->getExitCode() . "\n" . $combined
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Ошибка при запуске сборки: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Разбор строк CHUB_DEPLOY_* из stdout deploy.sh (удалённый репозиторий после pull).
     *
     * @return array{sha: string, short: string, remote: string, subject: string, author: string}
     */
    private static function parseDeployScriptMeta(string $output): array
    {
        $defaults = [
            'sha' => '',
            'short' => '',
            'remote' => '',
            'subject' => '',
            'author' => '',
        ];

        foreach (preg_split("/\r\n|\n|\r/", $output) as $line) {
            if (!str_starts_with($line, 'CHUB_DEPLOY_')) {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = substr($line, 0, $eq);
            $val = trim(substr($line, $eq + 1));

            if ($key === 'CHUB_DEPLOY_SHA') {
                $defaults['sha'] = $val;
            } elseif ($key === 'CHUB_DEPLOY_SHORT') {
                $defaults['short'] = $val;
            } elseif ($key === 'CHUB_DEPLOY_REMOTE') {
                $defaults['remote'] = $val;
            } elseif ($key === 'CHUB_DEPLOY_SUBJECT_B64') {
                $defaults['subject'] = self::decodeDeployB64Field($val);
            } elseif ($key === 'CHUB_DEPLOY_AUTHOR_B64') {
                $defaults['author'] = self::decodeDeployB64Field($val);
            }
        }

        return $defaults;
    }

    private static function decodeDeployB64Field(string $b64): string
    {
        if ($b64 === '') {
            return '';
        }

        $raw = base64_decode($b64, true);

        return is_string($raw) ? trim($raw) : '';
    }

    /**
     * @param  array{sha: string, short: string, remote: string, subject: string, author: string}  $meta
     */
    private static function githubCommitUrl(string $full_sha, string $remote_url): ?string
    {
        $full_sha = trim($full_sha);
        if (strlen($full_sha) < 7) {
            return null;
        }

        $override = trim((string) config('services.devbot.github_repo', ''));
        $pair = null;
        if ($override !== '') {
            $override = trim($override, '/');
            $parts = explode('/', $override, 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $pair = [$parts[0], $parts[1]];
            }
        }

        if ($pair === null) {
            $pair = self::githubOwnerRepoFromRemote(trim($remote_url));
        }

        if ($pair === null) {
            return null;
        }

        [$owner, $repo] = $pair;
        $repo = preg_replace('#\.git$#i', '', $repo);
        $repo = rtrim($repo, '/');

        return 'https://github.com/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/commit/' . $full_sha;
    }

    /**
     * owner/repo из remote.origin.url (разные форматы GitHub).
     *
     * @return array{0: string, 1: string}|null
     */
    private static function githubOwnerRepoFromRemote(string $remote_url): ?array
    {
        if ($remote_url === '') {
            return null;
        }

        // git@github.com:owner/repo.git или git@ssh.github.com:owner/repo.git
        if (preg_match('#^git@(?:ssh\.)?github\.com:([^/\s]+)/([^\s]+)$#i', $remote_url, $m)) {
            return [$m[1], $m[2]];
        }

        // ssh://git@github.com/owner/repo.git
        if (preg_match('#^ssh://git@(?:ssh\.)?github\.com/([^/\s]+)/([^\s]+)$#i', $remote_url, $m)) {
            return [$m[1], $m[2]];
        }

        // https://github.com/owner/repo(.git) — допускаем user@ в хосте
        if (preg_match('#^https?://(?:[^/\s]+@)?github\.com/([^/\s]+)/([^\s]+)$#i', $remote_url, $m)) {
            return [$m[1], $m[2]];
        }

        // подстраховка: любая подстрока вида github.com/owner/repo
        if (preg_match('#github\.com/([^/\s]+)/([^/\s]+?)(?:\.git)?(?:\s|$)#i', $remote_url, $m)) {
            return [$m[1], $m[2]];
        }

        return null;
    }

    /**
     * HTML для Rocket.Chat (support.bot → deliverToRocket с разметкой как у Telegram HTML).
     *
     * @param  array{sha: string, short: string, remote: string, subject: string, author: string}  $meta
     */
    private static function buildDeployRocketMessage(array $meta): string
    {
        $h = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $when = $h(now()->toDateTimeString());
        $subject = $meta['subject'] !== '' ? $h($meta['subject']) : '<i>(без темы)</i>';
        $sha = $meta['sha'];
        $short = $meta['short'] !== '' ? $meta['short'] : substr($sha, 0, 7);
        $url = self::githubCommitUrl($sha, $meta['remote']);

        $lines = [
            '<b>Деплой на production</b> · ' . $when,
            $subject,
        ];

        if ($meta['author'] !== '') {
            $lines[] = '<b>Автор:</b> ' . $h($meta['author']);
        }

        if ($url !== null && $short !== '') {
            $lines[] = '<a href="' . $h($url) . '">' . $h($short) . '</a>';
        } elseif ($sha !== '') {
            $lines[] = '<code>' . $h($sha) . '</code>';
        }

        return implode('<br>', $lines);
    }

    /**
     * @param  array{sha: string, short: string, remote: string, subject: string, author: string}  $meta
     */
    private static function buildDeployFlashMessage(array $meta): string
    {
        $author_suffix = $meta['author'] !== '' ? ' · ' . $meta['author'] : '';

        if ($meta['subject'] !== '' && $meta['short'] !== '') {
            return 'Деплой выполнен: ' . $meta['short'] . ' — ' . $meta['subject'] . $author_suffix;
        }

        if ($meta['short'] !== '') {
            return 'Деплой выполнен успешно (HEAD ' . $meta['short'] . ')' . $author_suffix;
        }

        return 'Деплой выполнен успешно' . $author_suffix;
    }
}
