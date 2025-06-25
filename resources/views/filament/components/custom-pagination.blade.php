@props([
    'paginator',
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-between mt-4']) }}>
    <div class="text-sm text-gray-700 dark:text-gray-300">
        {{ __('filament::pagination.showing') }}
        {{ $paginator->firstItem() }}
        {{ __('filament::pagination.to') }}
        {{ $paginator->lastItem() }}
        {{ __('filament::pagination.of') }}
        {{ $paginator->total() }}
        {{ __('filament::pagination.results') }}
    </div>

    <div class="flex space-x-1 rtl:space-x-reverse">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1 text-gray-400 dark:text-gray-600 cursor-not-allowed">
                {{ __('filament::pagination.previous') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                {{ __('filament::pagination.previous') }}
            </a>
        @endif

        @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="px-3 py-1 font-bold text-white bg-blue-500 dark:bg-blue-600 rounded">
                    {{ $page }}
                </span>
            @else
                <a href="{{ $url }}" class="px-3 py-1 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                {{ __('filament::pagination.next') }}
            </a>
        @else
            <span class="px-3 py-1 text-gray-400 dark:text-gray-600 cursor-not-allowed">
                {{ __('filament::pagination.next') }}
            </span>
        @endif
    </div>
</div>
