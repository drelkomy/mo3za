<div class="space-y-4">
    @foreach($stages as $stage)
        <div class="border rounded-lg p-4 {{ $stage->status === 'completed' ? 'bg-green-50 border-green-200' : ($stage->status === 'in_progress' ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200') }}">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        @if($stage->status === 'completed')
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        @elseif($stage->status === 'in_progress')
                            <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        @else
                            <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ $stage->stage_number }}</span>
                            </div>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">{{ $stage->title }}</h3>
                        <p class="text-sm text-gray-500">
                            الحالة: 
                            @switch($stage->status)
                                @case('pending')
                                    <span class="text-gray-600">في الانتظار</span>
                                    @break
                                @case('in_progress')
                                    <span class="text-yellow-600">قيد التنفيذ</span>
                                    @break
                                @case('completed')
                                    <span class="text-green-600">مكتملة</span>
                                    @break
                            @endswitch
                        </p>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    المرحلة {{ $stage->stage_number }} من {{ $task->total_stages }}
                </div>
            </div>
            
            @if($stage->description)
                <div class="mb-3">
                    <p class="text-sm text-gray-700">{{ $stage->description }}</p>
                </div>
            @endif
            
            @if($task->getMedia('task_attachments')->isNotEmpty())
                <div class="mt-3">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">مرفقات إثبات إنجاز المهمة ({{ $task->getMedia('task_attachments')->count() }}):</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($task->getMedia('payment_proofs') as $attachment)
                            <div class="flex items-center justify-between p-2 bg-white rounded border">
                                <div class="flex items-center space-x-2">
                                    <div class="flex-shrink-0">
                                        @if(str_contains($attachment->mime_type, 'image'))
                                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-900">{{ $attachment->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $attachment->human_readable_size }}</p>
                                    </div>
                                </div>
                                <div class="flex space-x-1">
                                    @if(str_contains($attachment->mime_type, 'image'))
                                        <button onclick="window.open('{{ $attachment->getUrl() }}', '_blank')" 
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200">
                                            عرض
                                        </button>
                                    @endif
                                    <a href="{{ $attachment->getUrl() }}" download="{{ $attachment->name }}"
                                       class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200">
                                        تحميل
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>