<?php namespace Zen\Chub\Api;

use Zen\Chub\Classes\System\CommandApp;

class CommandApi
{
    # http://axis/chub.api/CommandApi:exec?code=logs-rotate
    public function exec()
    {
        $code = trim((string) get('code'));
        if ($code === '') {
            return response()->json(['error' => 'Parameter code is required'], 400);
        }

        $meta = CommandApp::getResolvedCommandMeta($code);
        if (!$meta) {
            return response()->json(['error' => 'Command not found'], 404);
        }

        if (intval($meta['api_enabled'] ?? 0) !== 1) {
            return response()->json(['error' => 'API is disabled for this command'], 403);
        }

        if (intval($meta['active'] ?? 0) !== 1) {
            return response()->json(['error' => 'Command is inactive'], 403);
        }

        return CommandApp::exec($code);
    }
}