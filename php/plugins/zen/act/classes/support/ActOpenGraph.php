<?php namespace Zen\Act\Classes\Support;

use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Models\Act;

/**
 * Open Graph meta для серверного HTML (Telegram, мессенджеры).
 * Краулеры не выполняют JS — теги должны быть в blade.
 */
class ActOpenGraph
{
    public const SITE_NAME = 'Акт';

    public const DEFAULT_TITLE = 'Акт';

    public const DEFAULT_DESCRIPTION = 'Документ в Zen.Act';

    public const OG_IMAGE_PATH = '/plugins/zen/act/assets/og/act-og-default.png';

    public const OG_IMAGE_WIDTH = 1200;

    public const OG_IMAGE_HEIGHT = 630;

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     image: string,
     *     url: string,
     *     site_name: string,
     *     type: string,
     *     locale: string,
     *     image_width: int,
     *     image_height: int,
     *     restricted: bool
     * }|null
     */
    public static function forActId(?string $actId): ?array
    {
        if ($actId === null) {
            return null;
        }

        $actId = trim($actId);
        if ($actId === '') {
            return null;
        }

        $act = Act::find($actId);
        $pageUrl = self::absoluteUrl('/act_'.$actId);
        $imageUrl = self::absoluteUrl(self::OG_IMAGE_PATH);

        if ($act === null || ! self::isPubliclyReadable($actId)) {
            return self::meta(
                self::DEFAULT_TITLE,
                self::DEFAULT_DESCRIPTION,
                $imageUrl,
                $pageUrl,
                true,
            );
        }

        $name = trim((string) ($act->name ?? ''));
        $title = $name !== '' ? $name : self::DEFAULT_TITLE;

        return self::meta(
            $title,
            self::buildDescription($act),
            $imageUrl,
            $pageUrl,
            false,
        );
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     image: string,
     *     url: string,
     *     site_name: string,
     *     type: string,
     *     locale: string,
     *     image_width: int,
     *     image_height: int,
     *     restricted: bool
     * }
     */
    private static function meta(
        string $title,
        string $description,
        string $imageUrl,
        string $pageUrl,
        bool $restricted,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'image' => $imageUrl,
            'url' => $pageUrl,
            'site_name' => self::SITE_NAME,
            'type' => 'website',
            'locale' => 'ru_RU',
            'image_width' => self::OG_IMAGE_WIDTH,
            'image_height' => self::OG_IMAGE_HEIGHT,
            'restricted' => $restricted,
        ];
    }

    private static function isPubliclyReadable(string $actId): bool
    {
        return AccessApp::make()->can(null, $actId, 'act', $actId, 'read', false);
    }

    private static function buildDescription(Act $act): string
    {
        $description = trim((string) ($act->description ?? ''));
        if ($description !== '') {
            return self::truncate($description, 200);
        }

        $owner = $act->owner;
        if ($owner !== null) {
            $displayName = trim((string) ($owner->name ?? ''));
            if ($displayName !== '') {
                return 'Акт · '.$displayName;
            }

            $login = trim((string) ($owner->username ?? ''));
            if ($login !== '') {
                return 'Акт · @'.$login;
            }
        }

        return self::DEFAULT_DESCRIPTION;
    }

    private static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    private static function absoluteUrl(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if (! app()->runningInConsole()) {
            $request = request();
            if ($request !== null) {
                return rtrim($request->getSchemeAndHttpHost(), '/').$path;
            }
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl !== '') {
            return $appUrl.$path;
        }

        return $path;
    }
}
