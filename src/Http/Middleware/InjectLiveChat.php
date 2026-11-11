<?php

namespace Hibira\LiveChat\Http\Middleware;

use Closure;
use Hibira\LiveChat\LiveChat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * hibira.live-chat: puts the chat before </body> of every HTML page, so no
 * layout needs editing. Skips JSON, redirects, downloads, streamed responses,
 * the paths in hibira-live-chat.except and pages that already have it.
 */
class InjectLiveChat
{
    public function __construct(protected LiveChat $chat)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->wantsChat($request, $response)) {
            return $response;
        }

        $html = $this->chat->render($request)->toHtml();
        if ($html === '') {
            return $response;
        }

        $content = (string) $response->getContent();
        $at = strripos($content, '</body>');
        if ($at === false || str_contains($content, $this->chat->scriptUrl())) {
            return $response;
        }

        $response->setContent(substr($content, 0, $at).$html."\n".substr($content, $at));
        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
        }

        return $response;
    }

    protected function wantsChat(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->header('X-Inertia') || $request->header('X-Livewire')) {
            return false;
        }
        if ($response->getStatusCode() !== 200 || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return false;
        }
        if (! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            return false;
        }
        $except = (array) config('hibira-live-chat.except', []);

        return $except === [] || ! $request->is(...$except);
    }
}
