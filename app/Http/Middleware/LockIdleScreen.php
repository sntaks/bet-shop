<?php

namespace App\Http\Middleware;

use App\Models\ShopSetting;
use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the screen after the idle minutes in Settings: every request (Livewire ones included,
 * as this runs as persistent panel middleware) counts as activity, and a locked session can only
 * reach the unlock screen or log out until the same user enters their password.
 */
class LockIdleScreen
{
    public const string LOCKED = 'screen.locked';

    public const string LAST_ACTIVITY = 'screen.last_activity_at';

    /**
     * Routes a locked session may still reach.
     */
    private const array OPEN_WHILE_LOCKED = ['filament.shop.unlock', 'filament.shop.auth.logout'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $session = $request->session();

        if (! $session->get(self::LOCKED) && $this->hasBeenIdleTooLong($session)) {
            self::lock($session);

            if ($request->isMethod('GET') && ! $request->routeIs(...self::OPEN_WHILE_LOCKED)) {
                $session->put('url.intended', $request->fullUrl());
            }
        }

        if ($session->get(self::LOCKED)) {
            return $request->routeIs(...self::OPEN_WHILE_LOCKED)
                ? $next($request)
                : redirect()->route('filament.shop.unlock');
        }

        $session->put(self::LAST_ACTIVITY, now()->getTimestamp());

        return $next($request);
    }

    public static function lock(Session $session): void
    {
        $session->put(self::LOCKED, true);
    }

    public static function unlock(Session $session): void
    {
        $session->forget(self::LOCKED);
        $session->put(self::LAST_ACTIVITY, now()->getTimestamp());
    }

    private function hasBeenIdleTooLong(Session $session): bool
    {
        $lastActivity = $session->get(self::LAST_ACTIVITY);

        if (! is_int($lastActivity)) {
            return false;
        }

        return now()->getTimestamp() - $lastActivity >= ShopSetting::current()->idle_lock_minutes * 60;
    }
}
