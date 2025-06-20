<x-filament-panels::page>
    <div class="space-y-6">
        <!-- معلومات المهمة -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">{{ $this->record->title }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <span class="text-sm text-gray-500">منشئ المهمة:</span>
                    <p class="font-medium">{{ $this->record->creator->name }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">تاريخ الانتهاء:</span>
                    <p class="font-medium">{{ $this->record->due_date?->format('Y-m-d') }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">التقدم:</span>
                    <p class="font-medium">{{ $this->record->progress }}%</p>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-gray-500">وصف المهمة:</span>
                <div class="prose max-w-none mt-2">
                    {!! $this->record->description !!}
                </div>
            </div>
        </div>

        <!-- جدول المراحل -->
        <div class="bg-white rounded-lg shadow">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>