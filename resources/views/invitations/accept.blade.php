<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        <h2 class="text-xl font-bold mb-2">دعوة للانضمام إلى فريق {{ $invitation->sender->name }}</h2>
        <p>تمت دعوتك للانضمام إلى فريق {{ $invitation->sender->name }} على منصة معز.</p>
        
        @if($invitation->message)
        <div class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <p class="font-bold">رسالة من {{ $invitation->sender->name }}:</p>
            <p>{{ $invitation->message }}</p>
        </div>
        @endif
    </div>

    @if(Auth::check())
        <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
            @csrf
            <div class="flex items-center justify-end mt-4">
                <x-primary-button class="ms-4">
                    {{ __('قبول الدعوة') }}
                </x-primary-button>
            </div>
        </form>
    @else
        <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
            @csrf

            <!-- الاسم -->
            <div>
                <x-input-label for="name" :value="__('الاسم')" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $invitation->name)" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <!-- البريد الإلكتروني -->
            <div class="mt-4">
                <x-input-label for="email" :value="__('البريد الإلكتروني')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="$invitation->email" required autocomplete="username" readonly />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- كلمة المرور -->
            <div class="mt-4">
                <x-input-label for="password" :value="__('كلمة المرور')" />

                <x-text-input id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- تأكيد كلمة المرور -->
            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('تأكيد كلمة المرور')" />

                <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a href="{{ route('invitations.reject', $invitation->token) }}" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                    {{ __('رفض الدعوة') }}
                </a>

                <x-primary-button class="ms-4">
                    {{ __('قبول الدعوة') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>