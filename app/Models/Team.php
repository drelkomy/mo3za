<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // تحسين الأداء بإضافة العلاقات المستخدمة بشكل متكرر
    protected $with = ['owner'];

    // إضافة نطاقات للاستعلامات المتكررة
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function($q) use ($userId) {
            $q->where('owner_id', $userId)
              ->orWhereHas('members', function($subQ) use ($userId) {
                  $subQ->where('user_id', $userId);
              });
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    // دوال مساعدة لتحسين الأداء
    public function getMembersCount(): int
    {
        // استخدام التخزين المؤقت لتحسين الأداء
        $cacheKey = "team_{$this->id}_members_count";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function() {
            return $this->members()->count();
        });
    }

    public function hasMember(int $userId): bool
    {
        // استخدام exists بدلاً من count لتحسين الأداء
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function addMember(int $userId, string $role = 'member'): bool
    {
        if (!$this->hasMember($userId)) {
            $this->members()->attach($userId, ['role' => $role]);
            // حذف التخزين المؤقت عند تغيير الأعضاء
            Cache::forget("team_{$this->id}_members_count");
            return true;
        }
        return false;
    }

    public function removeMember(int $userId): bool
    {
        $result = $this->members()->detach($userId);
        // حذف التخزين المؤقت عند تغيير الأعضاء
        Cache::forget("team_{$this->id}_members_count");
        return $result > 0;
    }

    public function getPendingInvitationsCount(): int
    {
        return $this->invitations()->where('status', 'pending')->count();
    }
}