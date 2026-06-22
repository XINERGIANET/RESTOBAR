@props([
    'size' => 'md',          
    'variant' => 'primary',
    'startIcon' => null,
    'endIcon' => null,
    'className' => '',
    'disabled' => false,
])

@php
    // Base classes
    $base = 'inline-flex items-center justify-center font-medium gap-2 rounded-xl transition';

    // Size map
    $sizeMap = [
        'sm' => 'px-4 py-3 text-sm',
        'md' => 'px-5 py-3.5 text-sm',
        'icon' => 'h-10 w-10 p-0 text-sm',
    ];
    $sizeClass = $sizeMap[$size] ?? $sizeMap['md'];

    // Variant map
    $variantMap = [
        'primary' => 'bg-[#FF4622] text-white shadow-theme-xs hover:bg-[#C43B25] disabled:opacity-50',
        'outline' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300',
        'eliminate' => 'bg-error-500 text-white shadow-theme-xs hover:bg-error-700 disabled:bg-error-300 dark:bg-error-600 dark:hover:bg-error-700',
        'edit' => 'bg-yellow-500 text-white shadow-theme-xs hover:bg-yellow-600 disabled:bg-yellow-300 dark:bg-yellow-600 dark:hover:bg-yellow-700',
        'create' => 'bg-green-500 text-black shadow-theme-xs hover:bg-green-600 disabled:bg-green-300 dark:bg-green-600 dark:hover:bg-green-700',
        'dark' => 'bg-gray-600 text-white shadow-theme-xs hover:bg-gray-800 disabled:bg-gray-300',
    ];
    $variantClass = $variantMap[$variant] ?? $variantMap['primary'];

    // disabled classes
    $disabledClass = $disabled ? 'cursor-not-allowed opacity-50' : '';

    // final classes (merge user className too)
    // Ensure hover classes are at the end for proper specificity
    $classes = trim("{$base} {$sizeClass} {$variantClass} {$className} {$disabledClass}");
@endphp

<button
    {{ $attributes->merge(['class' => $classes, 'type' => $attributes->get('type', 'button')]) }}
    @if($variant === 'eliminate')
        style="background-color: rgb(240, 68, 56);" onmouseover="this.style.backgroundColor='rgb(180, 35, 24)'" onmouseout="this.style.backgroundColor='rgb(240, 68, 56)'"
    @endif
    @if($variant === 'create')
        style="background-color: #12f00e; color: #111827;" onmouseover="this.style.backgroundColor='#0f990b'" onmouseout="this.style.backgroundColor='#12f00e'"
    @endif
    @if($variant === 'edit')
        style="background-color: #f59e0b; color: #111827;" onmouseover="this.style.backgroundColor='#d97706'" onmouseout="this.style.backgroundColor='#f59e0b'"
    @endif
    @if($disabled) disabled @endif
>
    {{-- start icon: priority — named slot 'startIcon' first, then startIcon prop if it's a HtmlString --}}
    @if (isset($__env) && $slot->isEmpty() === false) @endif

    @hasSection('startIcon')
        <span class="flex items-center">
            @yield('startIcon')
        </span>
    @elseif($startIcon)
        <span class="flex items-center">{!! $startIcon !!}</span>
    @endif

    {{-- main slot --}}
    {{ $slot }}

    {{-- end icon: named slot 'endIcon' first, then endIcon prop --}}
    @hasSection('endIcon')
        <span class="flex items-center">
            @yield('endIcon')
        </span>
    @elseif($endIcon)
        <span class="flex items-center">{!! $endIcon !!}</span>
    @endif
</button>

