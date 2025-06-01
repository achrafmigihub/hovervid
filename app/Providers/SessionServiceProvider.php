<?php

namespace App\Providers;

use App\Services\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the custom PostgreSQL session driver
        Session::extend('postgres', function ($app) {
            // Resolve the database connection
            $connection = $app->make('db')->connection(
                config('session.connection')
            );

            // Get the session table name from the config
            $table = config('session.table', 'sessions');
            
            // Get the session lifetime from the config
            $lifetime = config('session.lifetime', 120);

            // Create a new instance of our custom session handler
            return new DatabaseSessionHandler(
                $connection, $table, $lifetime
            );
        });
    }
} 
