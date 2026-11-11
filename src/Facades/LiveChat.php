<?php

namespace Hibira\LiveChat\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Hibira\LiveChat\LiveChat hideWhen(?\Closure $callback)
 * @method static \Hibira\LiveChat\LiveChat describeUsing(?\Closure $callback)
 * @method static \Hibira\LiveChat\LiveChat identifyUsing(?\Closure $callback)
 * @method static bool shouldShow(?\Illuminate\Http\Request $request = null)
 * @method static array|null identity(?\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static array attributes(?\Illuminate\Http\Request $request = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString render(?\Illuminate\Http\Request $request = null, array $options = [])
 * @method static string workspace()
 * @method static string host()
 *
 * @see \Hibira\LiveChat\LiveChat
 */
class LiveChat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hibira\LiveChat\LiveChat::class;
    }
}
