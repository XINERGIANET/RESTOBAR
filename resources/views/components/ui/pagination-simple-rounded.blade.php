<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
    <div class="flex flex-col flex-1 gap-1 sm:hidden">
        <div class="flex justify-between w-full">
            @if ($paginator->onFirstPage())
                <span
                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-200 cursor-default leading-5 rounded-xl">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 leading-5 rounded-xl active:bg-gray-100 transition ease-in-out duration-150"
                    style="hover: { color: #334155; }">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                    class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-700 bg-white border border-gray-200 leading-5 rounded-xl active:bg-gray-100 transition ease-in-out duration-150">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span
                    class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-500 bg-white border border-gray-200 cursor-default leading-5 rounded-xl">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
            Página
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $paginator->currentPage() }}</span>
            de
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $paginator->lastPage() }}</span>
        </div>
    </div>

    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-end">
        <style>
            .pagination-arrow {
                color: #9CA3AF;
                transition: color 0.2s;
            }

            .pagination-arrow:hover {
                color: #334155 !important;
            }

            .pagination-arrow-disabled {
                color: #D1D5DB;
            }
        </style>
        <div>
            <span class="relative z-0 inline-flex gap-1 items-center">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span
                            class="relative inline-flex items-center h-10 w-10 justify-center pagination-arrow-disabled cursor-default leading-5"
                            aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                        class="relative inline-flex items-center h-10 w-10 justify-center pagination-arrow transition ease-in-out duration-150"
                        aria-label="{{ __('pagination.previous') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                @endif

                {{-- Pagination Elements --}}
                <div class="flex items-center gap-2 mx-2">
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span
                                    class="relative inline-flex items-center h-10 w-10 justify-center text-sm font-medium text-gray-700 bg-white border border-gray-200 cursor-default leading-5 rounded-xl"
                                    style="border: 1.5px solid #E5E7EB;">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span
                                            class="relative inline-flex items-center h-10 w-10 justify-center text-sm font-bold rounded-xl leading-5 cursor-default text-white"
                                            style="background-color: #FF4622;">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}"
                                        class="relative inline-flex items-center h-10 w-10 justify-center text-sm font-medium text-gray-700 bg-white rounded-xl leading-5 hover:text-gray-500 focus:z-10 focus:outline-none focus:ring ring-gray-300 active:bg-gray-100 transition ease-in-out duration-150"
                                        aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                        style="border: 1.5px solid #E5E7EB;">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                        class="relative inline-flex items-center h-10 w-10 justify-center pagination-arrow transition ease-in-out duration-150"
                        aria-label="{{ __('pagination.next') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span
                            class="relative inline-flex items-center h-10 w-10 justify-center pagination-arrow-disabled cursor-default leading-5"
                            aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M9 5l7 7-7 7"></path>
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </div>
</nav>

