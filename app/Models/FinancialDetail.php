<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'whatsapp_number',
        'phone_number',
        'email',
        'bank_account_details',
    ];

    /**
     * Get the user that owns the financial details.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}