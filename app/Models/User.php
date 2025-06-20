<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'gender', 'birthdate', 
        'is_active', 'user_type', 'avatar_url'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birthdate' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            if (!$user->hasRole('admin')) {
                app(\App\Services\SubscriptionService::class)->createTrialSubscription($user);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? url('storage/' . $this->avatar_url) : null;
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'receiver_id');
    }

    public function givenRewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'giver_id');
    }

    public function receivedRewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'receiver_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'sender_id');
    }

    protected $activeSubscriptionCache = null;

    public function activeSubscription()
    {
        if ($this->activeSubscriptionCache === null) {
            $this->activeSubscriptionCache = $this->subscriptions()->where('status', 'active')->latest()->first();
        }
        return $this->activeSubscriptionCache;
    }
    
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function canAddTasks(): bool
    {
        return app(\App\Services\SubscriptionService::class)->canAddTasks($this);
    }

    public function canAddParticipants(): bool
    {
        return app(\App\Services\SubscriptionService::class)->canAddParticipants($this);
    }

    public function incrementTasksCreated(): bool
    {
        return app(\App\Services\SubscriptionService::class)->incrementTasksCreated($this);
    }

    public function incrementParticipantsCreated(): bool
    {
        return app(\App\Services\SubscriptionService::class)->incrementParticipantsCreated($this);
    }
}