<?php

namespace App\Console\Commands;

use App\Jobs\ClearAndOptimizeCacheJob;
use Illuminate\Console\Command;

class ClearAndOptimizeCacheCommand extends Command
{
    protected $signature = 'cache:clear-optimize';
    protected $description = 'مسح جميع أنواع الكاش وإعادة تحسين التطبيق';

    public function handle()
    {
        $this->info('بدء تنظيف وتحسين الكاش...');
        
        ClearAndOptimizeCacheJob::dispatch();
        
        $this->info('تم إرسال مهمة تنظيف الكاش إلى الطابور');
        
        return 0;
    }
}