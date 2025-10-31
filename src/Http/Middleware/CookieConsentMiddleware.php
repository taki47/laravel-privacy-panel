<?php

namespace Taki47\CookieConsent\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cookie;

/**
 * Middleware that injects the user's cookie consent preferences
 * into all rendered views.
 *
 * This middleware checks for the presence of the `cookie_consent` cookie.
 * If it exists, it decodes the stored consent preferences (in JSON format).
 * If not found, it falls back to a default configuration where only
 * "necessary" cookies are enabled.
 *
 * The consent data is then shared with all Blade views via
 * the global `cookieConsent` variable, allowing UI components
 * such as banners or scripts to react dynamically.
 */
class CookieConsentMiddleware
{
    /**
     * Handle an incoming HTTP request and share cookie consent data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Attempt to read the user's consent preferences from the cookie
        $cookie = Cookie::get('cookie_consent');

        // If not found, provide default preferences
        $consent = $cookie
            ? json_decode($cookie, true)
            : [
                'necessary' => true,
                'statistics' => false,
                'marketing' => false,
            ];

        // Make consent data available to all views
        view()->share('cookieConsent', $consent);

        // Continue request handling
        return $next($request);
    }
}
