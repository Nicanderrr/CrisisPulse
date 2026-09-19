<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\View;
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
        View::composer('*', function ($view): void {
            $view->with([
                'brandLogoUrl' => SystemSetting::logoUrl(),
                'loginMediaUrl' => SystemSetting::loginMediaUrl(),
                'loginMediaType' => SystemSetting::valueFor('login_media_type'),
            ]);
        });
    }
}
