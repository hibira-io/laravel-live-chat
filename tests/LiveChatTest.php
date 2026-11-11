<?php

namespace Hibira\LiveChat\Tests;

use Hibira\LiveChat\Facades\LiveChat;
use Hibira\LiveChat\LiveChat as Chat;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

class LiveChatTest extends TestCase
{
    protected function user(array $attributes = []): User
    {
        $user = new User(array_merge(['name' => 'Aoi Tanaka', 'email' => 'aoi@example.com'], $attributes));
        $user->id = 42;

        return $user;
    }

    public function test_component_prints_the_script_tag(): void
    {
        $html = Blade::render('<x-hibira-live-chat />');

        $this->assertStringContainsString('<script src="https://chat.hibira.io/w/v1.js" data-workspace="ws_test123" data-navigate-once async></script>', $html);
        $this->assertStringContainsString('hibira-livechat:ready', $html, 'the opener for your own buttons');
        $this->assertStringNotContainsString('data-user-', $html);
        $this->assertStringNotContainsString('data-lang', $html, 'browser language by default');
    }

    public function test_options_from_config_and_component(): void
    {
        config(['hibira-live-chat.color' => '#e5484d', 'hibira-live-chat.launcher' => false, 'hibira-live-chat.lang' => 'app']);
        app()->setLocale('zh_TW');

        $html = Blade::render('<x-hibira-live-chat />');
        $this->assertStringContainsString('data-color="#e5484d"', $html);
        $this->assertStringContainsString('data-launcher="none"', $html);
        $this->assertStringContainsString('data-lang="zh-tw"', $html);

        $html = Blade::render('<x-hibira-live-chat color="#123456" :launcher="true" lang="ja" nonce="abc" />');
        $this->assertStringContainsString('data-color="#123456"', $html);
        $this->assertStringNotContainsString('data-launcher', $html);
        $this->assertStringContainsString('data-lang="ja"', $html);
        $this->assertStringContainsString('nonce="abc"', $html);
        $this->assertSame(2, substr_count($html, 'nonce="abc"'), 'both scripts carry the nonce');
    }

    public function test_a_bad_colour_is_dropped(): void
    {
        $html = Blade::render('<x-hibira-live-chat color="red;onload=alert(1)" />');

        $this->assertStringNotContainsString('data-color', $html);
    }

    public function test_signed_in_users_are_verified_with_the_identity_secret(): void
    {
        $this->actingAs($this->user());
        $this->assertStringNotContainsString('data-user-id', Blade::render('@hibiraLiveChat'), 'no secret, no identity');

        config(['hibira-live-chat.identity_secret' => 'lcid_secret']);
        $html = Blade::render('@hibiraLiveChat');

        $this->assertStringContainsString('data-user-id="42"', $html);
        $this->assertStringContainsString('data-user-hash="'.hash_hmac('sha256', '42', 'lcid_secret').'"', $html);
        $this->assertStringContainsString('data-user-name="Aoi Tanaka"', $html);
        $this->assertStringContainsString('data-user-email="aoi@example.com"', $html);
    }

    public function test_identity_for_spas_and_custom_ids(): void
    {
        config(['hibira-live-chat.identity_secret' => 'lcid_secret']);
        LiveChat::identifyUsing(fn ($u) => 'customer-'.$u->id)
            ->describeUsing(fn ($u) => ['name' => 'Aoi', 'email' => null]);

        $identity = LiveChat::identity($this->user());

        $this->assertSame([
            'id' => 'customer-42',
            'hash' => Chat::userHash('lcid_secret', 'customer-42'),
            'name' => 'Aoi',
            'email' => null,
        ], $identity);
        $this->assertNull(LiveChat::identity(null));
    }

    public function test_hidden_for_staff_and_by_callback(): void
    {
        Gate::define('hideHibiraLiveChat', fn ($user) => $user->email === 'staff@example.com');

        $this->actingAs($this->user(['email' => 'staff@example.com']));
        $this->assertSame('', Blade::render('<x-hibira-live-chat />'));

        $this->actingAs($this->user());
        $this->assertNotSame('', Blade::render('<x-hibira-live-chat />'));

        LiveChat::hideWhen(fn ($request) => $request->is('checkout'));
        $this->get('/checkout');
        $this->assertSame('', Blade::render('<x-hibira-live-chat />'));
    }

    public function test_hidden_without_a_workspace_or_when_disabled(): void
    {
        config(['hibira-live-chat.workspace' => 'not-a-workspace']);
        $this->assertSame('', Blade::render('<x-hibira-live-chat />'));

        config(['hibira-live-chat.workspace' => 'ws_test123', 'hibira-live-chat.enabled' => false]);
        $this->assertSame('', Blade::render('<x-hibira-live-chat />'));
    }

    public function test_open_button(): void
    {
        $this->assertSame(
            '<button type="button" class="hibira-live-chat-open btn" data-topic="sales">Talk to sales</button>',
            trim(Blade::render('<x-hibira-live-chat-open topic="sales" class="btn">Talk to sales</x-hibira-live-chat-open>'))
        );

        app()->setLocale('ja');
        $this->assertStringContainsString('>チャットで問い合わせる</button>', Blade::render('<x-hibira-live-chat-open />'));
    }

    public function test_another_host(): void
    {
        config(['hibira-live-chat.host' => 'http://chat.localhost:8080/']);

        $this->assertStringContainsString('src="http://chat.localhost:8080/w/v1.js"', Blade::render('<x-hibira-live-chat />'));
    }
}
