@props([
    'name' => '',
    'value' => '',
    'required' => false,
    'useAlpine' => false,
])

@php
    $timeValue = '';
    if ($value) {
        try {
            $timeValue = \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Exception $e) {
            $timeValue = is_string($value) ? substr($value, 0, 5) : '';
        }
    }
@endphp

<input
    type="time"
    name="{{ $name }}"
    value="{{ $timeValue }}"
    @if($required) required @endif
    @if($useAlpine) x-model="form.{{ $name }}" @endif
    onclick="typeof this.showPicker === 'function' && this.showPicker()"
    {{ $attributes->except(['class'])->merge([
        'class' => 'cursor-pointer transition-all duration-200 h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 pl-[62px] pr-10 text-sm font-medium text-gray-800 outline-none shadow-sm hover:border-gray-300 hover:shadow focus:border-[#FF4622] focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 dark:hover:border-gray-600 dark:focus:border-[#FF4622] dark:focus:ring-brand-500/30 [&::-webkit-datetime-edit]:text-gray-800 dark:[&::-webkit-datetime-edit]:text-white/90 [&::-webkit-datetime-edit]:font-medium [&::-webkit-calendar-picker-indicator]:opacity-60 [&::-webkit-calendar-picker-indicator]:hover:opacity-100 [&::-webkit-calendar-picker-indicator]:cursor-pointer',
    ]) }}
>
