<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * المدن التابعة للمنطقة
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * المستخدمين في هذه المنطقة
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}