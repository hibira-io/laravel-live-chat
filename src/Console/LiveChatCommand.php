<?php

namespace Hibira\LiveChat\Console;

use Hibira\LiveChat\LiveChat;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;

class LiveChatCommand extends Command
{
    protected $signature = 'hibira:live-chat
        {--workspace= : Your workspace ID (ws_…) from Settings › Widget on chat.hibira.io}
        {--check : Only check the setup}';

    protected $description = 'Set up Hibira Live Chat: .env keys, then check that this site may show the chat';

    public function handle(Filesystem $files, LiveChat $chat): int
    {
        if (! $this->option('check')) {
            $workspace = (string) $this->option('workspace');
            if ($workspace !== '' && ! LiveChat::validWorkspace($workspace)) {
                $this->error("$workspace is not a workspace ID. It starts with ws_; find it in Settings › Widget on chat.hibira.io.");

                return self::FAILURE;
            }
            foreach (['.env' => $workspace, '.env.example' => ''] as $name => $value) {
                $this->writeEnv($files, base_path($name), $value);
            }
            if ($workspace !== '') {
                config(['hibira-live-chat.workspace' => $workspace]);
            }

            $this->line('');
            $this->line('  Show the chat with one of:');
            $this->line('  - <comment><x-hibira-live-chat /></comment> before </body> in your layout');
            $this->line('  - the <comment>hibira.live-chat</comment> middleware on the web group (bootstrap/app.php:');
            $this->line('    <comment>$middleware->web(append: [\Hibira\LiveChat\Http\Middleware\InjectLiveChat::class])</comment>)');
            $this->line('');
        }

        return $this->check($chat);
    }

    protected function check(LiveChat $chat): int
    {
        $workspace = $chat->workspace();
        if (! LiveChat::validWorkspace($workspace)) {
            $this->error('  ✗ HIBIRA_LIVECHAT_WORKSPACE is not set.');
            $this->line('    Copy the workspace ID (ws_…) from Settings › Widget on https://chat.hibira.io, or run php artisan hibira:live-chat --workspace=ws_…');

            return self::FAILURE;
        }
        $this->info("  ✓ Workspace $workspace");

        $secret = (string) config('hibira-live-chat.identity_secret');
        if ($secret === '') {
            $this->line('  - No identity secret: signed-in users chat as anonymous visitors. Set HIBIRA_LIVECHAT_IDENTITY_SECRET (Settings › Widget › Verified visitors, Starter plan and up) to show them by name.');
        } elseif (! str_starts_with($secret, 'lcid_')) {
            $this->warn('  ! HIBIRA_LIVECHAT_IDENTITY_SECRET does not start with lcid_. Copy it again from Settings › Widget › Verified visitors.');
        } else {
            $this->info('  ✓ Identity secret set: signed-in users are verified');
        }

        if (! config('hibira-live-chat.enabled', true)) {
            $this->warn('  ! HIBIRA_LIVECHAT_ENABLED=false: the chat is hidden everywhere.');
        }

        $origin = $this->origin((string) config('app.url'));
        if ($origin === '') {
            $this->warn('  ! APP_URL is not set, so the site could not be checked.');

            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(10)->withHeaders(['Origin' => $origin])
                ->get($chat->host().'/widget/v1/config', ['workspace_id' => $workspace]);
        } catch (\Throwable $e) {
            $this->warn('  ! Could not reach '.$chat->host().': '.$e->getMessage());

            return self::SUCCESS;
        }

        if ($response->successful()) {
            $this->info("  ✓ $origin may show the chat");

            return self::SUCCESS;
        }

        $error = $response->json('error');
        $message = is_string($error) ? $error : (string) $response->body();
        $this->error("  ✗ $origin can't show the chat: ".trim($message));
        if (in_array($response->status(), [403, 404], true)) {
            $this->line("    Add $origin under Settings › Widget › Sites on https://chat.hibira.io (and check the workspace ID).");
        }

        return self::FAILURE;
    }

    protected function writeEnv(Filesystem $files, string $path, string $workspace): void
    {
        if (! $files->exists($path)) {
            return;
        }
        $env = $files->get($path);
        $before = $env;

        if (preg_match('/^HIBIRA_LIVECHAT_WORKSPACE=(.*)$/m', $env, $m)) {
            if ($workspace !== '' && trim($m[1]) !== $workspace) {
                $env = (string) preg_replace('/^HIBIRA_LIVECHAT_WORKSPACE=.*$/m', 'HIBIRA_LIVECHAT_WORKSPACE='.$workspace, $env, 1);
            }
        } else {
            $env = rtrim($env, "\n")."\n\nHIBIRA_LIVECHAT_WORKSPACE=$workspace\n";
        }
        if (! preg_match('/^HIBIRA_LIVECHAT_IDENTITY_SECRET=/m', $env)) {
            $env = rtrim($env, "\n")."\nHIBIRA_LIVECHAT_IDENTITY_SECRET=\n";
        }

        if ($env !== $before) {
            $files->put($path, $env);
            $this->info('Updated '.basename($path).'.');
        }
    }

    protected function origin(string $url): string
    {
        $p = parse_url($url);
        if (empty($p['scheme']) || empty($p['host'])) {
            return '';
        }
        $o = strtolower($p['scheme']).'://'.strtolower($p['host']);

        return isset($p['port']) ? $o.':'.$p['port'] : $o;
    }
}
