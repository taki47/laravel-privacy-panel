<?php
namespace Taki47\CookieConsent;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Taki47\CookieConsent\Http\Middleware\CookieConsentMiddleware;

class CookieConsentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Views
        $this->loadViewsFrom(__DIR__.'/resources/views', 'cookie-consent');

        // Routes
        Route::middleware('web')->group(__DIR__.'/routes/web.php');

        // Translations
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'cookie-consent');

        // Middleware
        $this->app['router']->pushMiddlewareToGroup('web', CookieConsentMiddleware::class);

        // cookiescancommand register
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Taki47\CookieConsent\Console\Commands\CookieScanCommand::class,
                \Taki47\CookieConsent\Console\Commands\CookieClearCacheCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/cookie-consent'),
        ], 'public');

    }

    public function register()
    {
        //
    }
}
