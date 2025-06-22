<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            الباقات المتاحة للاشتراك
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($this->getPackages() as $package)
                <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="text-center">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $package->name }}</h3>
                        <div class="text-3xl font-bold text-primary-600 mb-4">
                            {{ number_format($package->price, 2) }} ريال
                        </div>
                        
                        <div class="space-y-2 text-sm text-gray-600 mb-6">
                            <div class="flex justify-between">
                                <span>المهام:</span>
                                <span class="font-semibold">{{ $package->max_tasks }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>المراحل لكل مهمة:</span>
                                <span class="font-semibold">{{ $package->max_milestones_per_task }}</span>
                            </div>
                        </div>

                        @if($package->description)
                            <p class="text-gray-600 text-sm mb-4">{{ $package->description }}</p>
                        @endif

                        <form action="{{ route('payment.pay') }}" method="POST" class="w-full">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ $package->id }}">
                            <button 
                                type="submit"
                                class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-2 px-4 rounded-lg transition-colors"
                            >
                                اشترك الآن
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        @if($this->getPackages()->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-500">لا توجد باقات متاحة حالياً</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>