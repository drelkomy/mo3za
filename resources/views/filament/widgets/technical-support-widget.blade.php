<div class="p-4 bg-white rounded shadow">
    <h2 class="text-lg font-bold mb-2">بيانات التحويل البنكي</h2>
    @if(isset($financialData))
        <div class="mb-2">
            <span class="font-semibold">اسم البنك:</span>
            <span>{{ $financialData->bank_name ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">رقم الحساب:</span>
            <span>{{ $financialData->account_number ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">اسم صاحب الحساب:</span>
            <span>{{ $financialData->account_name ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">الآيبان:</span>
            <span>{{ $financialData->iban ?? '-' }}</span>
        </div>
        <div class="mb-2">
            <span class="font-semibold">ملاحظات:</span>
            <span>{{ $financialData->notes ?? '-' }}</span>
        </div>
    @else
        <div class="text-danger">لا توجد بيانات مالية متاحة حالياً.</div>
    @endif
</div>
