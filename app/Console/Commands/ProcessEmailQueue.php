<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessEmailQueue extends Command
{
    protected $signature = 'queue:work-emails {--timeout=60}';
    protected $description = 'Process email queue jobs';

    public function handle()
    {
        $this->info('Starting email queue worker...');
        
        Artisan::call('queue:work', [
            '--queue' => 'emails',
            '--timeout' => $this->option('timeout'),
            '--tries' => 3,
            '--delay' => 3,
            '--memory' => 128,
            '--sleep' => 3,
        ]);

        return 0;
    }
}