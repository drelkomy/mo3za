<x-filament-widgets::widget>
    <x-filament::section>
        <div class="text-center py-8">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">انتهى اشتراكك أو وصلت للحد الأقصى من المهام</h3>
            <div class="space-y-4 mb-6">
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-500">المهام المستخدمة</p>
                        <p class="text-lg font-medium text-gray-900">{{ $usage['tasks']['current'] }} / {{ $usage['tasks']['max'] }}</p>
                    </div>
                    <div class="w-1/2">
                        <div class="bg-gray-200 rounded-full h-2.5">
                            <div class="bg-primary-600 h-2.5 rounded-full" style="width: {{ ($usage['tasks']['current'] / $usage['tasks']['max']) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-500">الأعضاء المستخدمين</p>
                        <p class="text-lg font-medium text-gray-900">{{ $usage['members']['current'] }} / {{ $usage['members']['max'] }}</p>
                    </div>
                    <div class="w-1/2">
                        <div class="bg-gray-200 rounded-full h-2.5">
                            <div class="bg-primary-600 h-2.5 rounded-full" style="width: {{ ($usage['members']['current'] / $usage['members']['max']) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-500 mb-6">لقد وصلت للحد الأقصى من المهام في باقتك ({{ $usage['tasks']['current'] }} من {{ $usage['tasks']['max'] }}). يرجى الاشتراك في باقة جديدة لمواصلة إنشاء المهام وإدارة الفرق.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                @foreach(\App\Models\Package::where('is_active', true)->get() as $package)
                    <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                        <h4 class="font-semibold text-gray-900">{{ $package->name }}</h4>
                        <p class="text-2xl font-bold text-primary-600 my-2">{{ number_format($package->price) }} ريال</p>
                        <ul class="text-sm text-gray-600 space-y-1 mb-4">
                            <li>• {{ $package->max_tasks }} مهمة</li>

                            <li>• {{ $package->max_milestones_per_task }} مرحلة لكل مهمة</li>
                        </ul>
                        <a href="{{ route('paytabs.pay') }}?package_id={{ $package->id }}" 
                           class="block w-full bg-primary-600 text-white text-center py-2 px-4 rounded hover:bg-primary-700 transition-colors">
                            اشترك الآن
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>