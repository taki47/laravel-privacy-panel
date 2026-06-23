<?php

namespace Taki47\PrivacyPanel\Http\Middleware;

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
class PrivacyPanelMiddleware
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
        $cookie = Cookie::get('cookie-consent');

        // If not found, provide default preferences
        $storedConsent = $cookie ? json_decode($cookie, true) : null;
        $storedConsent = is_array($storedConsent) ? $storedConsent : [];

        $consent = [
            'necessary' => true,
            'statistics' => ($storedConsent['statistics'] ?? false) === true,
            'marketing' => ($storedConsent['marketing'] ?? false) === true,
        ];

        // Make consent data available to all views
        view()->share('privacyPanel', $consent);

        // Continue request handling
        return $next($request);
    }
}
