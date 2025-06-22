<x-filament-panels::page>
    <div class="space-y-4">
        <h1 class="text-2xl font-bold">مراحل المهمة: {{ $this->record->name }}</h1>

        @if ($this->record->milestones->count() > 0)
            @foreach ($this->record->milestones as $milestone)
                <x-filament::card>
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold">{{ $milestone->name }}</h3>
                        <span @class([
                            'px-2 py-1 text-xs font-medium rounded-full',
                            'text-gray-800 dark:text-gray-300 bg-gray-50 dark:bg-gray-500/10' => $milestone->status === 'pending',
                            'text-warning-800 dark:text-warning-300 bg-warning-50 dark:bg-warning-500/10' => $milestone->status === 'submitted',
                            'text-success-800 dark:text-success-300 bg-success-50 dark:bg-success-500/10' => $milestone->status === 'approved',
                            'text-danger-800 dark:text-danger-300 bg-danger-50 dark:bg-danger-500/10' => $milestone->status === 'rejected',
                        ])>
                            {{ $milestone->status }}
                        </span>
                    </div>
                    @if($milestone->description)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">{{ $milestone->description }}</p>
                    @endif
                </x-filament::card>
            @endforeach
        @else
            <x-filament::card>
                <p>لا توجد مراحل محددة لهذه المهمة.</p>
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>
