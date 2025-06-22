<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'package_id', 'order_id', 'transaction_id', 'amount', 'currency',
        'customer_name', 'customer_email', 'customer_phone', 'status', 'description',
        'notes', 'payment_method'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Eager-load frequently used relations to avoid N+1
    

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}