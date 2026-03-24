<?php
namespace Taki47\PrivacyPanel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Taki47\PrivacyPanel\Http\Middleware\PrivacyPanelMiddleware;

class PrivacyPanelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Views
        $this->loadViewsFrom(__DIR__.'/resources/views', 'privacy-panel');

        // Routes
        Route::middleware('web')->group(__DIR__.'/routes/web.php');

        // Translations
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'privacy-panel');

        // Middleware
        $this->app['router']->pushMiddlewareToGroup('web', PrivacyPanelMiddleware::class);

        // cookiescancommand register
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Taki47\PrivacyPanel\Console\Commands\CookieScanCommand::class,
                \Taki47\PrivacyPanel\Console\Commands\CookieClearCacheCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/privacy-panel'),
        ], 'public');

    }

    public function register()
    {
        //
    }
}
