<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LinkSubscriptionsToTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:link-teams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Links existing subscriptions to their corresponding owner\'s team.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to link subscriptions to teams...');

        $subscriptions = \App\Models\Subscription::with('user.ownedTeams')->whereNull('team_id')->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions found that need linking.');
            return;
        }

        $bar = $this->output->createProgressBar($subscriptions->count());
        $bar->start();

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            // Assuming each user owns one team where the subscription should be linked.
            if ($user && $team = $user->ownedTeams()->first()) {
                $subscription->update(['team_id' => $team->id]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nFinished linking subscriptions. Processed " . $subscriptions->count() . " subscriptions.");
    }
}
