<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        Paginator::defaultView('pagination.numbered');
        Paginator::defaultSimpleView('pagination.numbered');

        // Record last login without touching updated_at.
        Event::listen(Login::class, function (Login $event) {
            DB::table('users')->where('id', $event->user->getAuthIdentifier())->update(['last_login_at' => now()]);
        });
    }
}
