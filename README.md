# Hibira Live Chat for Laravel

[![ci](https://github.com/hibira-io/laravel-live-chat/actions/workflows/ci.yml/badge.svg)](https://github.com/hibira-io/laravel-live-chat/actions/workflows/ci.yml)

Puts the [Hibira Live Chat](https://chat.hibira.io/?utm_source=github&utm_medium=readme&utm_campaign=laravel-live-chat)
bubble on your Laravel site: AI answers from your help articles, translation
both ways, and one inbox for your team. Your signed-in users show up in the
inbox under their own name, verified on your server, so nobody can chat as
someone else.

One Blade component, or one middleware if you'd rather not touch your
layouts. Works with Blade, Livewire (including `wire:navigate`), Inertia
(React and Vue) and Filament front ends. Laravel 10 to 13, PHP 8.1+.

## Install

```sh
composer require hibira-io/laravel-live-chat
php artisan hibira:live-chat --workspace=ws_your_workspace_id
```

Find the workspace ID under Settings › Widget on
[chat.hibira.io](https://chat.hibira.io), and add your site's address
(`APP_URL`) under Sites there. The command writes the `.env` keys and checks
that your site may show the chat.

Then show it on every page, either in your layout, before `</body>`:

```blade
<x-hibira-live-chat />
```

or with the middleware, without editing any view (Laravel 11+, in
`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [\Hibira\LiveChat\Http\Middleware\InjectLiveChat::class]);
})
```

On Laravel 10, add `'hibira.live-chat'` to the `web` group in
`app/Http/Kernel.php`. The middleware adds the chat to HTML pages only, and
skips JSON, redirects, downloads, Inertia and Livewire requests, pages that
already have it and the paths in `except` (admin, Filament, Nova, Horizon,
Telescope and Pulse by default).

## Verified users

Copy the identity secret (`lcid_…`) from Settings › Widget › Verified
visitors (Starter plan and up) into `.env`:

```ini
HIBIRA_LIVECHAT_IDENTITY_SECRET=lcid_...
```

The signed-in user's ID is then signed with
`hash_hmac('sha256', $id, $secret)` on every page. The inbox shows their name
and email, and their chats follow them to other devices. The secret never
reaches the browser.

Change what is sent in a service provider:

```php
use Hibira\LiveChat\Facades\LiveChat;

LiveChat::identifyUsing(fn ($user) => 'customer-'.$user->id)
    ->describeUsing(fn ($user) => ['name' => $user->display_name, 'email' => $user->email]);
```

### Inertia and other SPAs

The component identifies the user on the first page load. When someone signs
in or out without a full page load, tell the widget. Share the identity in
`HandleInertiaRequests`:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'liveChat' => fn () => \Hibira\LiveChat\Facades\LiveChat::identity($request->user()),
    ];
}
```

```tsx
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function useLiveChatIdentity() {
    const { liveChat } = usePage<{ liveChat: { id: string; hash: string } | null }>().props;
    useEffect(() => {
        const sync = () => (liveChat ? window.HibiraLiveChat.identify(liveChat) : window.HibiraLiveChat.logout());
        if (window.HibiraLiveChat) sync();
        else window.addEventListener('hibira-livechat:ready', sync, { once: true });
    }, [liveChat?.id]);
}
```

## Hiding the chat

Your own staff don't need the bubble. Users this gate allows don't see it:

```php
Gate::define('hideHibiraLiveChat', fn ($user) => $user->is_admin);
```

Or hide it by request:

```php
LiveChat::hideWhen(fn (Request $request, $user) => $request->routeIs('checkout.*'));
```

`HIBIRA_LIVECHAT_ENABLED=false` hides it everywhere, for example locally.

## Opening it from your own buttons

```blade
<x-hibira-live-chat-open class="btn">Chat with us</x-hibira-live-chat-open>
<x-hibira-live-chat-open topic="sales">Talk to sales</x-hibira-live-chat-open>
<a href="#hibira-live-chat">Help</a>
```

`topic="sales"` marks a new conversation as a sales inquiry. Without a label
the button reads "Chat with us" in the app's language (en, ja, ko, zh_TW,
zh_CN, th, vi). In JavaScript: `HibiraLiveChat.open()`, `.close()`.

## Options

`<x-hibira-live-chat color="#e5484d" :launcher="false" lang="ja" />`
overrides the config for one page. Publish the config with
`php artisan vendor:publish --tag=hibira-live-chat-config`.

| Setting | `.env` | Default |
|---|---|---|
| `workspace` | `HIBIRA_LIVECHAT_WORKSPACE` | none |
| `identity_secret` | `HIBIRA_LIVECHAT_IDENTITY_SECRET` | none |
| `enabled` | `HIBIRA_LIVECHAT_ENABLED` | `true` |
| `color` | `HIBIRA_LIVECHAT_COLOR` | the colour set in Settings › Widget |
| `launcher` | `HIBIRA_LIVECHAT_LAUNCHER` | `true`; `false` leaves out the round button |
| `lang` | `HIBIRA_LIVECHAT_LANG` | `browser`; `app` follows `app()->getLocale()`, or a code such as `ja` |
| `guard` | | the default guard |
| `hide_ability` | | `hideHibiraLiveChat` |
| `except` | | admin, filament, nova, horizon, telescope, pulse paths (middleware only) |

With a CSP, the script tags get Laravel's `Vite::cspNonce()`, or pass
`nonce="..."`. Allow `https://chat.hibira.io` in `script-src`,
`connect-src`, `img-src` and `frame-src`, and `wss://*.hibira.io` in
`connect-src`.

`php artisan hibira:live-chat --check` checks the setup again: the workspace
ID, the identity secret and whether `APP_URL` is on the workspace's sites.

## Related

- [hibira-io/laravel](https://github.com/hibira-io/laravel): broadcasting,
  Echo, push notifications and waiting rooms on Hibira.
- [hibira-io/filament-support](https://github.com/hibira-io/filament-support):
  a self-hosted AI support desk inside Filament, with your own LLM key.

## Development

```sh
composer install
composer test
```

## License

[MIT](LICENSE)

## 中文摘要

Laravel 版的 Hibira Live Chat：在网站加上客服对话泡泡，AI 先根据你的帮助文章回答，
支持双向翻译，团队在同一个收件箱回复。`composer require hibira-io/laravel-live-chat`
之后运行 `php artisan hibira:live-chat --workspace=ws_…`，再在版面 `</body>` 前放
`<x-hibira-live-chat />`，或者把 `InjectLiveChat` middleware 加到 web 组，不必改任何视图。

- 填入身份密钥（`lcid_…`，Starter 方案以上）后，已登录的用户在服务器端签名，
  在收件箱以本人名字出现，别人无法冒充。Inertia / SPA 可以用 `LiveChat::identity()`
  搭配 `HibiraLiveChat.identify()`。
- `Gate::define('hideHibiraLiveChat', …)` 可以对员工隐藏，`LiveChat::hideWhen()`
  按请求隐藏。
- `<x-hibira-live-chat-open>` 按钮和 `#hibira-live-chat` 链接都能打开对话，按钮文字支持 7 种语言。
- `php artisan hibira:live-chat --check` 会检查工作区 ID、身份密钥，以及 `APP_URL` 是否已加入网站清单。
