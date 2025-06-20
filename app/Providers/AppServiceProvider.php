<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

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
        // مراقبة الطابور
        Queue::before(function (JobProcessing $event) {
            Log::info('Job started processing', [
                'job' => $event->job->resolveName(),
                'id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
            ]);
        });

        Queue::after(function (JobProcessed $event) {
            Log::info('Job processed successfully', [
                'job' => $event->job->resolveName(),
                'id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
            ]);
        });

        Queue::failing(function (JobFailed $event) {
            Log::error('Job failed', [
                'job' => $event->job->resolveName(),
                'id' => $event->job->getJobId(),
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}