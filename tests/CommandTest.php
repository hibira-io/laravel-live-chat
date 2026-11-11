<?php

namespace Hibira\LiveChat\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class CommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::put(base_path('.env'), "APP_NAME=Shop\n");
        File::put(base_path('.env.example'), "APP_NAME=Shop\n");
    }

    protected function tearDown(): void
    {
        File::delete([base_path('.env'), base_path('.env.example')]);
        parent::tearDown();
    }

    public function test_writes_env_and_checks_the_site(): void
    {
        Http::fake(['chat.hibira.io/widget/v1/config*' => Http::response(['title' => 'Shop'])]);

        $this->artisan('hibira:live-chat', ['--workspace' => 'ws_abc'])
            ->expectsOutputToContain('<x-hibira-live-chat />')
            ->expectsOutputToContain('https://shop.example.com may show the chat')
            ->assertSuccessful();

        $this->assertStringContainsString("HIBIRA_LIVECHAT_WORKSPACE=ws_abc\nHIBIRA_LIVECHAT_IDENTITY_SECRET=\n", File::get(base_path('.env')));
        $this->assertStringContainsString("HIBIRA_LIVECHAT_WORKSPACE=\n", File::get(base_path('.env.example')), 'no real ID in the example');
        Http::assertSent(fn ($r) => $r->hasHeader('Origin', 'https://shop.example.com')
            && str_contains($r->url(), 'workspace_id=ws_abc'));

        $this->artisan('hibira:live-chat', ['--workspace' => 'ws_abc'])->assertSuccessful();
        $this->assertSame(1, substr_count(File::get(base_path('.env')), 'HIBIRA_LIVECHAT_WORKSPACE='), 'runs again without duplicates');
    }

    public function test_reports_a_site_that_is_not_allowed(): void
    {
        Http::fake(['*' => Http::response(['error' => "this site is not allowed to use the workspace's chat; add it in the portal", 'code' => 'origin_not_allowed'], 403)]);

        $this->artisan('hibira:live-chat', ['--check' => true])
            ->expectsOutputToContain("this site is not allowed to use the workspace's chat")
            ->expectsOutputToContain('Add https://shop.example.com under Settings › Widget › Sites')
            ->assertFailed();
    }

    public function test_rejects_a_bad_workspace_id(): void
    {
        $this->artisan('hibira:live-chat', ['--workspace' => 'abc'])->assertFailed();
        $this->assertStringNotContainsString('HIBIRA_LIVECHAT', File::get(base_path('.env')));
    }
}
