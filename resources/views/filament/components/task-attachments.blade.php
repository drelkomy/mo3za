<div class="space-y-4">
    @if($attachments->isEmpty())
        <div class="text-center py-8">
            <p class="text-gray-500">لا توجد مرفقات إثبات إنجاز المهمة</p>
        </div>
    @else
        @foreach($attachments as $attachment)
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    @if(str_contains($attachment->mime_type, 'image'))
                        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    @else
                        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ $attachment->name }}</p>
                    <p class="text-xs text-gray-500">{{ $attachment->human_readable_size }}</p>
                </div>
            </div>
            <div class="flex space-x-2">
                @if(str_contains($attachment->mime_type, 'image'))
                    <button onclick="window.open('{{ $attachment->getUrl() }}', '_blank')" 
                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200">
                        عرض
                    </button>
                @endif
                <a href="{{ $attachment->getUrl() }}" download="{{ $attachment->name }}"
                   class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200">
                    تحميل
                </a>
            </div>
        </div>
        @endforeach
    @endif
</div>