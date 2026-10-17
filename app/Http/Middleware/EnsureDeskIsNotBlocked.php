<?php

namespace App\Http\Middleware;

use App\Services\DeskBlock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While KadiApi has shut this desk out (shop suspended or closed, or a bad key), every screen
 * shows the blocked page instead, so no action can be started.
 */
class EnsureDeskIsNotBlocked
{
    /**
     * Routes still reachable while the desk is blocked.
     */
    private const array OPEN_WHILE_BLOCKED = ['filament.shop.blocked', 'filament.shop.unlock', 'filament.shop.auth.logout'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (DeskBlock::isBlocked() && ! $request->routeIs(...self::OPEN_WHILE_BLOCKED)) {
            return redirect()->route('filament.shop.blocked');
        }

        return $next($request);
    }
}
