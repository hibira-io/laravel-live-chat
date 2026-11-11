<?php

namespace Hibira\LiveChat;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;

/**
 * Builds the Live Chat widget's script tag. Nothing here talks to Hibira:
 * the chat itself runs on chat.hibira.io; this only names the workspace and,
 * for a signed-in user, signs their ID with the identity secret.
 */
class LiveChat
{
    /** @var (Closure(Request, Authenticatable|null): bool)|null */
    protected ?Closure $hideWhen = null;

    /** @var (Closure(Authenticatable): array{name?: string|null, email?: string|null})|null */
    protected ?Closure $describeUsing = null;

    /** @var (Closure(Authenticatable): string|int)|null */
    protected ?Closure $idUsing = null;

    public function __construct(protected Application $app)
    {
    }

    /** Hide the chat when the callback returns true: fn (Request $request, ?Authenticatable $user) => bool. */
    public function hideWhen(?Closure $callback): static
    {
        $this->hideWhen = $callback;

        return $this;
    }

    /** The name and email the inbox shows: fn (Authenticatable $user) => ['name' => …, 'email' => …]. */
    public function describeUsing(?Closure $callback): static
    {
        $this->describeUsing = $callback;

        return $this;
    }

    /** The ID a user chats under (default getAuthIdentifier()): fn (Authenticatable $user) => string|int. */
    public function identifyUsing(?Closure $callback): static
    {
        $this->idUsing = $callback;

        return $this;
    }

    public function workspace(): string
    {
        return (string) $this->config('workspace');
    }

    public function host(): string
    {
        return rtrim((string) $this->config('host', 'https://chat.hibira.io'), '/');
    }

    public function scriptUrl(): string
    {
        return $this->host().'/w/v1.js';
    }

    public static function validWorkspace(?string $id): bool
    {
        return is_string($id) && preg_match('/^ws_[A-Za-z0-9_-]{1,64}$/', $id) === 1;
    }

    /** hex(HMAC-SHA256(identity secret, user ID)): what the widget checks for a verified visitor. */
    public static function userHash(string $secret, string|int $userId): string
    {
        return hash_hmac('sha256', (string) $userId, $secret);
    }

    /** Whether the chat shows on this request. */
    public function shouldShow(?Request $request = null): bool
    {
        if (! $this->config('enabled', true) || ! self::validWorkspace($this->workspace())) {
            return false;
        }
        $request ??= $this->app->bound('request') ? $this->app['request'] : null;
        $user = $this->user($request);

        if ($user !== null && ($ability = $this->config('hide_ability')) && Gate::has($ability)
            && Gate::forUser($user)->allows($ability)) {
            return false;
        }

        return ! ($this->hideWhen && $request && ($this->hideWhen)($request, $user));
    }

    /**
     * The verified-visitor fields for $user ({id, hash, name, email}), or null
     * without an identity secret. Hand it to the browser in an SPA (Inertia
     * props) and call HibiraLiveChat.identify(it).
     *
     * @return array{id: string, hash: string, name: string|null, email: string|null}|null
     */
    public function identity(?Authenticatable $user): ?array
    {
        $secret = (string) $this->config('identity_secret');
        if ($user === null || $secret === '') {
            return null;
        }
        $id = $this->idUsing ? ($this->idUsing)($user) : $user->getAuthIdentifier();
        if ($id === null || $id === '') {
            return null;
        }
        $about = $this->describeUsing ? ($this->describeUsing)($user) : [
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];

        return [
            'id' => (string) $id,
            'hash' => self::userHash($secret, $id),
            'name' => isset($about['name']) ? (string) $about['name'] : null,
            'email' => isset($about['email']) ? (string) $about['email'] : null,
        ];
    }

    /**
     * The script tag's attributes.
     *
     * @param  array{color?: string|null, launcher?: bool|null, lang?: string|null, nonce?: string|null}  $options
     * @return array<string, string|bool>
     */
    public function attributes(?Request $request = null, array $options = []): array
    {
        $request ??= $this->app->bound('request') ? $this->app['request'] : null;
        $a = [
            'src' => $this->scriptUrl(),
            'data-workspace' => $this->workspace(),
        ];

        $color = $options['color'] ?? $this->config('color');
        if (is_string($color) && preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($color))) {
            $a['data-color'] = trim($color);
        }

        $lang = $this->lang($options['lang'] ?? $this->config('lang', 'browser'));
        if ($lang !== null) {
            $a['data-lang'] = $lang;
        }

        if (! ($options['launcher'] ?? $this->config('launcher', true))) {
            $a['data-launcher'] = 'none';
        }

        if ($identity = $this->identity($this->user($request))) {
            $a['data-user-id'] = $identity['id'];
            $a['data-user-hash'] = $identity['hash'];
            if ($identity['name'] !== null && $identity['name'] !== '') {
                $a['data-user-name'] = $identity['name'];
            }
            if ($identity['email'] !== null && $identity['email'] !== '') {
                $a['data-user-email'] = $identity['email'];
            }
        }

        $nonce = $options['nonce'] ?? (class_exists(Vite::class) ? Vite::cspNonce() : null);
        if ($nonce) {
            $a['nonce'] = (string) $nonce;
        }

        // Livewire's wire:navigate keeps the chat open between pages.
        $a['data-navigate-once'] = true;
        $a['async'] = true;

        return $a;
    }

    /**
     * The widget's script tag, plus the small script that lets your own
     * buttons and #hibira-live-chat links open it. Empty when the chat is
     * hidden on this request.
     *
     * @param  array{color?: string|null, launcher?: bool|null, lang?: string|null, nonce?: string|null}  $options
     */
    public function render(?Request $request = null, array $options = []): HtmlString
    {
        if (! $this->shouldShow($request)) {
            return new HtmlString('');
        }
        $attributes = $this->attributes($request, $options);
        $nonce = isset($attributes['nonce']) ? ' nonce="'.e($attributes['nonce']).'"' : '';

        return new HtmlString(
            '<script'.$this->htmlAttributes($attributes).'></script>'."\n"
            .'<script data-navigate-once'.$nonce.'>'.self::OPENER.'</script>'
        );
    }

    /** @param array<string, string|bool> $attributes */
    protected function htmlAttributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === true) {
                $html .= ' '.$name;
            } elseif ($value !== false) {
                $html .= ' '.$name.'="'.e($value).'"';
            }
        }

        return $html;
    }

    protected function lang(?string $lang): ?string
    {
        if ($lang === null || $lang === '' || $lang === 'browser') {
            return null;
        }
        if ($lang === 'app') {
            $lang = (string) $this->app->getLocale();
        }

        return strtolower(str_replace('_', '-', $lang));
    }

    protected function user(?Request $request): ?Authenticatable
    {
        if (! $this->app->bound('auth')) {
            return null;
        }
        $guard = $this->config('guard');
        try {
            $user = $this->app['auth']->guard($guard)->user();
        } catch (\Throwable) {
            $user = $request?->user($guard);
        }

        return $user instanceof Authenticatable ? $user : null;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get('hibira-live-chat.'.$key, $default);
    }

    /**
     * Opens the chat from .hibira-live-chat-open elements and
     * href="#hibira-live-chat" links, also before the widget has loaded.
     * data-topic="sales" marks a new conversation as a sales inquiry.
     */
    public const OPENER = '(function(){if(window.__hibiraLiveChatOpener)return;window.__hibiraLiveChatOpener=1;var p=null;'
        .'function o(t){var x=t==="sales"?{topic:"sales"}:undefined;if(window.HibiraLiveChat){window.HibiraLiveChat.open(x)}else{p=x||{}}}'
        .'window.addEventListener("hibira-livechat:ready",function(){if(p){window.HibiraLiveChat.open(p.topic?p:undefined);p=null}});'
        .'document.addEventListener("click",function(e){var el=e.target&&e.target.closest?e.target.closest(".hibira-live-chat-open,a[href=\"#hibira-live-chat\"]"):null;'
        .'if(!el)return;e.preventDefault();o(el.getAttribute("data-topic"))})})();';
}
