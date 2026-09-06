<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogHttpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $request->headers->set('X-Request-Id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        if ($this->shouldSkip($request)) {
            $response = $next($request);
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        }

        $started = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        Log::info('http.request', [
            'event' => 'http.request',
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'query' => $request->query(),
        ]);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('up')
            || $request->is('livewire/*')
            || $request->is('css/*')
            || $request->is('js/*')
            || $request->is('fonts/*');
    }
}
