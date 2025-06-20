<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $title,
        public string $message,
        public string $type = 'info',
        public ?array $data = null,
        public ?string $actionUrl = null
    ) {}

    public function handle(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'data' => $this->data,
            'action_url' => $this->actionUrl,
        ]);
    }
}