<?php

namespace App\Http\Requests\Api;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscriptionId = $this->input('subscription_id');
        $subscription = Subscription::find($subscriptionId);
        
        return auth()->check() && $subscription && $subscription->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'subscription_id' => 'required|exists:subscriptions,id'
        ];
    }

    public function messages(): array
    {
        return [
            'subscription_id.required' => 'معرف الاشتراك مطلوب',
            'subscription_id.exists' => 'الاشتراك غير موجود'
        ];
    }
}