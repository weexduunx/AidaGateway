<?php

namespace Weexduunx\AidaGateway;

use Illuminate\Support\ServiceProvider;
use Weexduunx\AidaGateway\AidaGateway;

class AidaGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__.'/../config/aida-gateway.php', 'aida-gateway'
        );

        // Register the main class to use with the facade
        $this->app->singleton('aida-gateway', function ($app) {
            return new AidaGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/aida-gateway.php' => config_path('aida-gateway.php'),
        ], 'aida-config');

        // Publish migrations
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'aida-migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load views (if needed in the future)
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'aida-gateway');

        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add custom commands here
            ]);
        }
    }
}
