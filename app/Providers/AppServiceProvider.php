<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // تسجيل Task Observer
        \App\Models\Task::observe(\App\Observers\TaskObserver::class);
        
        // Fix Composer autoloader compatibility issue
        if (app()->environment('production')) {
            View::composer('*', function ($view) {
                if (str_contains($view->getName(), 'exceptions.renderer')) {
                    return response()->view('errors.500', [
                        'message' => 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى.'
                    ], 500);
                }
            });
        }
    }
}