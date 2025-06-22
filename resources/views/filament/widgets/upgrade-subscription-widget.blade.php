<x-filament-widgets::widget>
    <x-filament::card>
        <h2 class="text-lg font-semibold">{{ __('انتهى اشتراكك') }}</h2>
        <p class="text-sm text-gray-500">{{ __('اختر باقة جديدة للاستمرار.') }}</p>

        <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-3">
            @foreach ($this->packages as $package)
                <div class="p-4 border rounded-lg">
                    <h3 class="text-xl font-bold">{{ $package->name }}</h3>
                    <p class="my-2 text-3xl font-extrabold">{{ $package->price }} {{ __('SAR') }}</p>
                    <ul class="space-y-2 text-sm">
                        <li><span class="font-semibold">{{ $package->max_tasks }}</span> {{ __('Tasks') }}</li>
                        <li><span class="font-semibold">{{ $package->max_milestones_per_task }}</span> {{ __('Milestones per Task') }}</li>
                    </ul>
                    <a href="{{ route('payment.pay', ['package_id' => $package->id]) }}" 
                       class="block w-full px-4 py-2 mt-4 font-semibold text-center text-white bg-primary-600 rounded-lg hover:bg-primary-700">
                        {{ __('اشترك الآن') }}
                    </a>
                </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament-widgets::widget>
