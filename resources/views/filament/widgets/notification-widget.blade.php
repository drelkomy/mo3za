<div class="fixed top-4 left-4 z-50">
    <a href="{{ \App\Filament\Resources\NotificationResource::getUrl() }}" 
       class="relative inline-flex items-center p-3 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300">
        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path d="m17.418 3.623-.018-.008a6.713 6.713 0 0 0-2.4-.569V2a2 2 0 1 0-4 0v1.046c-.782.161-1.593.372-2.418.569l-.018.008A2.167 2.167 0 0 0 7 5.765v6.456c0 .173-.099.334-.256.445L5.528 13.4A.725.725 0 0 0 5.5 14.5h9a.725.725 0 0 0-.028-1.1l-1.216-.734c-.157-.111-.256-.272-.256-.445V5.765a2.167 2.167 0 0 0-1.584-2.142ZM8 17a2 2 0 1 0 4 0H8Z"/>
        </svg>
        @php
            $unreadCount = auth()->user()?->notifications()->unread()->count() ?? 0;
        @endphp
        @if($unreadCount > 0)
            <span class="sr-only">الإشعارات</span>
            <div class="absolute inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 border-2 border-white rounded-full -top-2 -end-2">
                {{ $unreadCount }}
            </div>
        @endif
    </a>
</div>

<script>
    setInterval(() => {
        Livewire.emit('$refresh');
    }, 30000);
</script>