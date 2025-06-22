<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessPasswordResetQueue extends Command
{
    protected $signature = 'queue:process-emails {--timeout=30}';
    protected $description = 'Process password reset email queue';

    public function handle()
    {
        $timeout = $this->option('timeout');
        
        $this->info("Processing email queue for {$timeout} seconds...");
        
        $exitCode = $this->call('queue:work', [
            '--queue' => 'emails',
            '--timeout' => $timeout,
            '--tries' => 3,
            '--delay' => 3,
            '--memory' => 128,
            '--sleep' => 2,
            '--max-jobs' => 10,
            '--stop-when-empty' => true,
        ]);

        if ($exitCode === 0) {
            $this->info('Email queue processed successfully');
        } else {
            $this->error('Email queue processing failed');
        }

        return $exitCode;
    }
}