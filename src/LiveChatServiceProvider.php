<?php

namespace Hibira\LiveChat;

use Hibira\LiveChat\Console\LiveChatCommand;
use Hibira\LiveChat\Http\Middleware\InjectLiveChat;
use Hibira\LiveChat\View\Components\LiveChat as LiveChatComponent;
use Hibira\LiveChat\View\Components\OpenButton;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class LiveChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hibira-live-chat.php', 'hibira-live-chat');
        $this->app->singleton(LiveChat::class, fn ($app) => new LiveChat($app));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'hibira-live-chat');

        Blade::component('hibira-live-chat', LiveChatComponent::class);
        Blade::component('hibira-live-chat-open', OpenButton::class);
        Blade::directive('hibiraLiveChat', fn () => '<?php echo app(\Hibira\LiveChat\LiveChat::class)->render(); ?>');

        $this->app['router']->aliasMiddleware('hibira.live-chat', InjectLiveChat::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/hibira-live-chat.php' => config_path('hibira-live-chat.php')], 'hibira-live-chat-config');
            $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/hibira-live-chat')], 'hibira-live-chat-lang');
            $this->commands([LiveChatCommand::class]);
        }
    }
}
