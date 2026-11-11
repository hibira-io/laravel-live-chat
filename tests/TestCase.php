<?php

namespace Hibira\LiveChat\Tests;

use Hibira\LiveChat\LiveChatServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LiveChatServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('hibira-live-chat.workspace', 'ws_test123');
        $app['config']->set('app.url', 'https://shop.example.com');
    }
}
