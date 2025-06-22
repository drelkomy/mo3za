<x-filament-widgets::widget>
    <x-filament::card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ __('الباقة التجريبية') }}</h2>
                <p class="text-sm text-gray-500">{{ __('ابدأ تجربتك المجانية واحصل على 3 مهام.') }}</p>
            </div>
            <x-filament::button wire:click="startTrial">
                {{ __('ابدأ التجربة') }}
            </x-filament::button>
        </div>
    </x-filament::card>
</x-filament-widgets::widget>
