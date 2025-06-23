<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Task;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Auth\Passwords\CanResetPassword;

class User extends Authenticatable implements FilamentUser, HasMedia, HasAvatar, \Illuminate\Contracts\Auth\CanResetPassword
{
    // المنطقة التي ينتمي إليها المستخدم
    public function area()
    {
        return $this->belongsTo(\App\Models\Area::class, 'area_id');
    }

    // المدينة التي ينتمي إليها المستخدم
    public function city()
    {
        return $this->belongsTo(\App\Models\City::class, 'city_id');
    }

    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia, HasRoles, CanResetPassword;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'gender', 'birthdate', 
        'is_active', 'user_type', 'avatar_url',
        'area_id', 'city_id', 'trial_used'
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
            // Assign role based on user_type upon creation
            if ($user->user_type === 'member' && !$user->hasRole('member')) {
                $user->assignRole('member');
            }


        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

        public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::url($this->avatar_url) : null;
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

    public function isOwnerOf(Team $team): bool
    {
        return $this->id === $team->owner_id;
    }

    public function isMemberOf(Team $team): bool
    {
        return $team->members()->where('user_id', $this->id)->exists() || $this->isOwnerOf($team);
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

    public function receivedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'receiver_id');
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

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    /**
     * Get the active subscription for the user.
     * Returns the most recent active subscription.
     */
    public function getActiveSubscriptionAttribute()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->latest('created_at')
            ->first();
    }
    
    public function activeSubscriptionRelation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest('created_at');
    }

    public function hasActiveSubscription(): bool
    {
        // استخدام cache للاستعلامات المتكررة
        return cache()->remember(
            "user_{$this->id}_has_active_subscription",
            300, // 5 دقائق
            fn() => $this->activeSubscriptionRelation()->exists()
        );
    }

    public function canAddTasks(): bool
    {
        $subscription = $this->activeSubscription;

        if (!$subscription || $subscription->isExpired()) {
            return false;
        }

        return true;
    }

    public function canAddTeamMembers(): bool
    {
        $subscription = $this->activeSubscription;

        if (!$subscription || $subscription->isExpired()) {
            return false;
        }

        return true;
    }

    public function canManageTeam(): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $subscription = $this->activeSubscription;

        if (!$subscription || !$subscription->package) {
            return false;
        }

        $taskLimit = $subscription->package->task_limit;

        if ($taskLimit == 0) { // 0 means unlimited
            return true;
        }

        $currentTaskCount = Task::where('subscription_id', $subscription->id)->count();

        return $currentTaskCount < $taskLimit;
    }


    public function sendPasswordResetNotification($token)
    {
        // Send via queue for better performance
        $this->notify(new \App\Notifications\ResetPasswordCustom($token));
        \Illuminate\Support\Facades\Log::info('Password reset notification queued', [
            'user_id' => $this->id,
            'email' => $this->email
        ]);
    }

}