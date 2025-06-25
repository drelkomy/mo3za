<x-filament-panels::page>
    <div class="max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-800">نسيت كلمة المرور</h2>
        @if (session('status'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md">
                {{ session('status') }}
            </div>
        @endif
        <form method="POST" action="{{ route('filament.admin.auth.password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                <input type="email" name="email" id="email" placeholder="البريد الإلكتروني" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                @error('email')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="w-full py-2 px-4 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                إرسال رابط إعادة التعيين
            </button>
        </form>
    </div>
</x-filament-panels::page>
