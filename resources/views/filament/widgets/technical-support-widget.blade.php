<div class="p-4 bg-white rounded shadow">
    <h2 class="text-lg font-bold mb-4">بيانات الدعم الفني</h2>
    @if(isset($financialData))
        <div class="mb-2">
            <span class="font-semibold">رقم الواتساب:</span>
            <span>{{ $financialData->whatsapp_number ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">رقم الهاتف:</span>
            <span>{{ $financialData->phone_number ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">البريد الإلكتروني:</span>
            <span>{{ $financialData->email ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">تفاصيل الحساب البنكي:</span>
            <span>{{ $financialData->bank_account_details ?? '-' }}</span>
        </div>
    @else
        <div class="text-danger">لا توجد بيانات مالية متاحة حالياً.</div>
    @endif
</div>
