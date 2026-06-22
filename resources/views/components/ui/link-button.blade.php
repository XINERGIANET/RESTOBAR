@props([
    'href' => '#',
    'size' => 'md',
    'variant' => 'primary',
    'startIcon' => null,
    'endIcon' => null,
    'className' => '',
    'disabled' => false,
])

@php
    $base = 'inline-flex items-center justify-center font-medium gap-2 rounded-xl transition';

    $sizeMap = [
        'sm' => 'px-4 py-3 text-sm',
        'md' => 'px-5 py-3.5 text-sm',
        'icon' => 'h-10 w-10 p-0 text-sm',
    ];
    $sizeClass = $sizeMap[$size] ?? $sizeMap['md'];

    $variantMap = [
        'primary' => 'bg-[#FF4622] text-white shadow-theme-xs hover:bg-[#C43B25] disabled:opacity-50',
        'outline' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300',
        'eliminate' => 'bg-error-500 text-white shadow-theme-xs hover:bg-error-700 disabled:bg-error-300 dark:bg-error-600 dark:hover:bg-error-700',
        'edit' => 'bg-yellow-500 text-white shadow-theme-xs hover:bg-yellow-600 disabled:bg-yellow-300 dark:bg-yellow-600 dark:hover:bg-yellow-700',
        'create' => 'bg-green-500 text-black shadow-theme-xs hover:bg-green-600 disabled:bg-green-300 dark:bg-green-600 dark:hover:bg-green-700',
    ];
    $variantClass = $variantMap[$variant] ?? $variantMap['primary'];

    $disabledClass = $disabled ? 'cursor-not-allowed opacity-50 pointer-events-none' : '';

    $classes = trim("{$base} {$sizeClass} {$variantClass} {$className} {$disabledClass}");
@endphp

<a
    href="{{ $disabled ? '#' : $href }}"
    {{ $attributes->merge(['class' => $classes]) }}
    @if($disabled) aria-disabled="true" tabindex="-1" @endif
    @if($variant === 'eliminate')
        style="background-color: rgb(240, 68, 56);" onmouseover="this.style.backgroundColor='rgb(180, 35, 24)'" onmouseout="this.style.backgroundColor='rgb(240, 68, 56)'"
    @endif
    @if($variant === 'create')
        style="background-color: #12f00e; color: #111827;" onmouseover="this.style.backgroundColor='#0f990b'" onmouseout="this.style.backgroundColor='#12f00e'"
    @endif
    @if($variant === 'edit')
        style="background-color: #f59e0b; color: #111827;" onmouseover="this.style.backgroundColor='#d97706'" onmouseout="this.style.backgroundColor='#f59e0b'"
    @endif
>
    @if($startIcon)
        <span class="flex items-center">{!! $startIcon !!}</span>
    @endif
    {{ $slot }}

    @if($endIcon)
        <span class="flex items-center">{!! $endIcon !!}</span>
    @endif
</a>

