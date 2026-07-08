<?php namespace Zen\Act\Controllers;

use Illuminate\Routing\Controller;
use Zen\Act\Classes\Support\ActOpenGraph;

/**
 * Одностраничное приложение «Акт» (Vue 3 + Vite).
 */
class AppController extends Controller
{
    public function index()
    {
        $og = ActOpenGraph::forActId($this->resolveActUuidFromRequest());

        return view('zen.act::app', [
            'og' => $og,
        ]);
    }

    private function resolveActUuidFromRequest(): ?string
    {
        $path = trim((string) request()->path(), '/');
        if (preg_match('/^act_([0-9a-fA-F-]{36})$/', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
