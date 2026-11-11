<?php

namespace Hibira\LiveChat\Tests;

use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $page = '<html><body><h1>Shop</h1></body></html>';
        Route::middleware('hibira.live-chat')->group(function () use ($page) {
            Route::get('/', fn () => $page);
            Route::get('/admin/orders', fn () => $page);
            Route::get('/api/orders', fn () => ['ok' => true]);
            Route::get('/already', fn () => '<html><body><x></x><script src="https://chat.hibira.io/w/v1.js" data-workspace="ws_test123"></script></body></html>');
            Route::get('/away', fn () => redirect('/'));
            Route::get('/missing', fn () => abort(404));
            Route::post('/form', fn () => $page);
        });
    }

    public function test_injects_before_body_end(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1>Shop</h1><script src="https://chat\.hibira\.io/w/v1\.js" data-workspace="ws_test123"[^>]*></script>\n<script data-navigate-once>.*</script>\n</body></html>#s', $html);
        $this->assertSame(1, substr_count($html, '/w/v1.js'));
    }

    public function test_skips_other_responses(): void
    {
        $this->assertStringNotContainsString('w/v1.js', $this->get('/admin/orders')->getContent(), 'except paths');
        $this->assertStringNotContainsString('w/v1.js', $this->get('/api/orders')->getContent(), 'JSON');
        $this->assertSame(1, substr_count($this->get('/already')->getContent(), 'w/v1.js'), 'already on the page');
        $this->get('/away')->assertRedirect('/');
        $this->assertStringNotContainsString('w/v1.js', (string) $this->get('/missing')->getContent());
        $this->assertStringNotContainsString('w/v1.js', $this->post('/form')->getContent());
        $this->assertStringNotContainsString('w/v1.js', $this->get('/', ['X-Inertia' => 'true'])->getContent(), 'Inertia visits are JSON');
    }
}
