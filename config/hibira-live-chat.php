<?php

return [

    // Your workspace ID (ws_…), from Settings › Widget on chat.hibira.io.
    'workspace' => env('HIBIRA_LIVECHAT_WORKSPACE'),

    // The identity secret (lcid_…) from Settings › Widget › Verified
    // visitors. With it, signed-in users appear in the inbox under their
    // own name and keep their chats across devices. Keep it server side.
    'identity_secret' => env('HIBIRA_LIVECHAT_IDENTITY_SECRET'),

    // false hides the chat everywhere, for example in local development.
    'enabled' => (bool) env('HIBIRA_LIVECHAT_ENABLED', true),

    // The chat's colour (#rgb … #rrggbbaa). Empty: the colour set in
    // Settings › Widget.
    'color' => env('HIBIRA_LIVECHAT_COLOR'),

    // false leaves out the round button: open the chat from your own
    // buttons with <x-hibira-live-chat-open> or HibiraLiveChat.open().
    'launcher' => (bool) env('HIBIRA_LIVECHAT_LAUNCHER', true),

    // "browser": the visitor's browser language. "app": the app's locale
    // (app()->getLocale()) on each request. Or a fixed code such as "ja".
    'lang' => env('HIBIRA_LIVECHAT_LANG', 'browser'),

    // The auth guard whose user is the visitor. null: the default guard.
    'guard' => null,

    // Users this gate ability allows don't see the chat, for example your
    // own staff: Gate::define('hideHibiraLiveChat', fn ($user) => $user->is_admin).
    'hide_ability' => 'hideHibiraLiveChat',

    // The hibira.live-chat middleware skips these paths (Request::is patterns).
    'except' => [
        'admin', 'admin/*',
        'filament', 'filament/*',
        'nova', 'nova/*',
        'horizon', 'horizon/*',
        'telescope', 'telescope/*',
        'pulse', 'pulse/*',
    ],

    // Where Live Chat runs.
    'host' => env('HIBIRA_LIVECHAT_HOST', 'https://chat.hibira.io'),

];
