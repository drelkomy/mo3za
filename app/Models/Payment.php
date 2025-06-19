<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'package_id',
        'order_id',
        'transaction_id',
        'amount',
        'currency',
        'payment_method',
        'customer_name',
        'customer_email',
        'customer_phone',
        'description',
        'notes',
        'status',
        'payment_result',
        'response_status',
        'callback_data',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'callback_data' => 'json',
        'metadata' => 'json',
    ];

    /**
     * Get the user that made the payment (subscriber).
     * العضو هو من يقوم بالاشتراك
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package that was purchased in this payment.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the subscription created from this payment.
     * الاشتراك المرتبط بعملية الدفع هذه
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}