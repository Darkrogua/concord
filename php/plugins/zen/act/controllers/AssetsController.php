<?php namespace Zen\Act\Controllers;

use Auth;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Zen\Act\Classes\Support\ActAssets;
use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Models\Act;

class AssetsController extends Controller
{
    public function show(string $act_id, string $block_id, string $image_id)
    {
        $act_id = trim($act_id);
        $block_id = trim($block_id);
        $image_id = trim($image_id);

        if ($act_id === '' || $block_id === '' || $image_id === '') {
            abort(404);
        }

        if (Act::find($act_id) === null) {
            abort(404);
        }

        $front_view = filter_var(request()->input('front_view') ?? request()->input('preview') ?? false, FILTER_VALIDATE_BOOLEAN);
        $session_login = null;
        $user = Auth::user();
        if ($user) {
            $login = trim((string) ($user->username ?? ''));
            $session_login = $login !== '' ? $login : null;
        }

        $as_viewer = trim((string) (request()->input('as_viewer') ?? ''));
        $as_viewer = $as_viewer !== '' ? $as_viewer : null;

        $is_mine = $user !== null
            && ($act = Act::find($act_id)) !== null
            && $act->owner_id !== null
            && (int) $act->owner_id === (int) $user->id;

        $effective_login = AccessApp::make()->resolveViewerLogin(
            $session_login,
            $act_id,
            $front_view,
            $is_mine,
            $as_viewer
        );

        if (! AccessApp::make()->can($effective_login, $act_id, 'block', $block_id, 'read', $front_view)) {
            abort(403);
        }

        $path = ActAssets::make()->resolvePath($act_id, $block_id, $image_id);
        if ($path === null) {
            abort(404);
        }

        $mime = ActAssets::make()->mimeForPath($path);

        return new BinaryFileResponse($path, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
