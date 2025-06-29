<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClearAndOptimizeCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            Log::info('🧹 بدء تنظيف وتحسين الكاش');

            // 🗑️ مسح الكاش من النظام
            Cache::flush();
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('filament:optimize-clear');

            // ⚡ إعادة كاش Laravel
            Artisan::call('config:cache');
            Artisan::call('route:cache');


            // ⚡ كاش Filament
            Artisan::call('filament:optimize');

            Log::info('✅ تم تنظيف وتحسين كاش Laravel وFilament بنجاح');

        } catch (\Exception $e) {
            Log::error('❌ خطأ في تنظيف الكاش: ' . $e->getMessage());
            throw $e;
        }
    }
}
