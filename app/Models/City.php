<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Eager-load area to avoid N+1 when listing cities with their area name
    protected $with = ['area'];

    /**
     * المنطقة التي تنتمي إليها المدينة
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * المستخدمين في هذه المدينة
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}