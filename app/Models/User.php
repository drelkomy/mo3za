<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'birthdate',
        'is_active',
        'user_type',
        'avatar_url',
        'area_id',
        'city_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birthdate' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Check if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->user_type === 'admin';
    }

    /**
     * Get the area that the user belongs to.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get the city that the user belongs to.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the teams owned by the user.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Get the teams that the user is a member of.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the financial details associated with the user.
     */
    public function financialDetail(): HasOne
    {
        return $this->hasOne(FinancialDetail::class);
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }
    
    /**
     * Get the payments made by the user.
     * المدفوعات التي قام بها المستخدم
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    
    /**
     * Get the subscriptions owned by the user.
     * الاشتراكات التي يملكها المستخدم
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
    
    /**
     * Get the user's active subscription if any.
     * الحصول على الاشتراك النشط للمستخدم إن وجد
     */
    public function activeSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->latest()
            ->first();
    }
    
    /**
     * Check if user has an active subscription
     * التحقق من وجود اشتراك نشط للمستخدم
     *
     * @return bool
     */
    public function hasActiveSubscription(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && !$subscription->isExpired();
    }
    
    /**
     * Check if user can add more tasks
     * التحقق من إمكانية إضافة مهام جديدة
     *
     * @return bool
     */
    public function canAddTasks(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && $subscription->canAddTasks();
    }
    
    /**
     * Check if user can add more participants
     * التحقق من إمكانية إضافة مشاركين جدد
     *
     * @return bool
     */
    public function canAddParticipants(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && $subscription->canAddParticipants();
    }
    
    /**
     * Check if user can add more milestones to a task
     * التحقق من إمكانية إضافة مراحل جديدة للمهمة
     *
     * @return bool
     */
    public function canAddMilestones(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription && $subscription->canAddMilestones();
    }
    
    /**
     * Increment tasks created count in active subscription
     * زيادة عدد المهام المنشأة في الاشتراك النشط
     *
     * @return bool
     */
    public function incrementTasksCreated(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->incrementTasksCreated() : false;
    }
    
    /**
     * Increment participants created count in active subscription
     * زيادة عدد المشاركين المنشأين في الاشتراك النشط
     *
     * @return bool
     */
    public function incrementParticipantsCreated(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->incrementParticipantsCreated() : false;
    }
}