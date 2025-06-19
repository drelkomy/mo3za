<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Models\Package;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Payment;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;
    
    protected function afterCreate(): void
    {
        $subscription = $this->record;

        // Create a corresponding payment record for the manual subscription
        $payment = Payment::create([
            'user_id' => $subscription->user_id,
            'amount' => $subscription->price_paid,
            'status' => 'completed',
            'payment_method' => 'cash',
            'transaction_id' => 'manual_' . uniqid(), // Add a unique ID for reference
            'notes' => 'تم إنشاء الدفعة يدويًا من لوحة التحكم',
        ]);

        // Link the payment to the subscription
        $subscription->update(['payment_id' => $payment->id]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['start_date'] = Carbon::now();

        if (isset($data['package_id']) && $package = Package::find($data['package_id'])) {
            $data['end_date'] = Carbon::now()->addDays($package->duration_in_days ?? 30);
            $data['price_paid'] = $package->price ?? 0;
        } else {
            $data['end_date'] = Carbon::now()->addDays(30);
            $data['price_paid'] = 0;
        }

        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}