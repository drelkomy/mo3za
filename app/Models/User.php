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
use Illuminate\Database\Eloquent\Relations\MorphMany;


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
        $value = $this->avatar_url ?? null;

        if (!$value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            // إزالة التكرار إذا وجد
            if (preg_match_all('#https://www\.moezez\.com/storage/#', $value, $matches)) {
                if (count($matches[0]) > 1) {
                    $parts = explode('https://www.moezez.com/storage/', $value);
                    return 'https://www.moezez.com/storage/' . end($parts);
                }
            }
            return $value;
        }

        return Storage::url($value);
    }

    /**
     * Get the full URL for the user's avatar.
     *
     * @return string|null
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $value = $this->attributes['avatar_url'] ?? null;

        if (!$value) {
            return null;
        }

        // إذا كانت القيمة URL كامل جاهز (https...) نعيدها كما هي
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // إذا كانت مجرد مسار نسبي، نستخدم Storage::url()
        return \Illuminate\Support\Facades\Storage::url($value);
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

    public function customNotifications(): HasMany
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

    public function mediaItems(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    /**
     * Get the active subscription for the user.
     * Returns the most recent active subscription.
     */
    public function getActiveSubscriptionAttribute()
    {
        // تحقق مما إذا كانت العلاقة محملة مسبقًا لتجنب الاستعلامات الإضافية
        if ($this->relationLoaded('activeSubscriptionRelation')) {
            return $this->activeSubscriptionRelation;
        }
        
        return cache()->remember("user_{$this->id}_active_subscription", now()->addMinutes(10), function () {
            return $this->subscriptions()
                ->where('status', 'active')
                ->latest('created_at')
                ->first();
        });
    }
    
    public function activeSubscriptionRelation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest('created_at');
    }

    public function hasActiveSubscription(): bool
    {
        // تحقق مما إذا كانت العلاقة محملة مسبقًا لتجنب الاستعلامات الإضافية
        if ($this->relationLoaded('activeSubscriptionRelation')) {
            return $this->activeSubscriptionRelation !== null;
        }
        
        // استخدام cache للاستعلامات المتكررة
        return cache()->remember(
            "user_{$this->id}_has_active_subscription",
            300, // 5 دقائق
            fn() => $this->activeSubscriptionRelation()->exists()
        );
    }

    public function hasValidSubscription(): bool
    {
        // تحقق مما إذا كانت العلاقة محملة مسبقًا لتجنب الاستعلامات الإضافية
        if ($this->relationLoaded('activeSubscriptionRelation')) {
            $subscription = $this->activeSubscriptionRelation;
        } else {
            $subscription = $this->activeSubscription;
        }
        return $subscription && !$subscription->isExpired();
    }

    public function canAddTasks(): bool
    {
        return $this->hasValidSubscription();
    }

    public function canAddTeamMembers(): bool
    {
        return $this->hasValidSubscription();
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
        // Send via queue for better performance using job
        \App\Jobs\SendPasswordResetJob::dispatch($this, $token);
        // Clear any cached data related to password reset for this user
        \Illuminate\Support\Facades\Cache::forget("password_reset_{$this->id}");
        \Illuminate\Support\Facades\Log::info('Password reset job dispatched', [
            'user_id' => $this->id,
            'email' => $this->email
        ]);
    }

    public function scopeWithActiveSubscription($query)
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->where('status', 'active');
        });
    }

}
