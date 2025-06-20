<div class="p-4">
    <h3 class="text-lg font-semibold mb-3">أعضاء الفريق</h3>
    
    @if($team->members->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($team->members as $member)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border">
                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center text-white font-semibold">
                                {{ substr($member->name, 0, 1) }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $member->name }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                {{ $member->email }}
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            @if($team->owner_id === auth()->id())
                                <x-filament::button
                                    wire:click="assignTask({{ $member->id }})"
                                    size="sm"
                                    color="primary"
                                >
                                    إسناد مهمة
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">لا يوجد أعضاء في الفريق حالياً</p>
        </div>
    @endif
</div>