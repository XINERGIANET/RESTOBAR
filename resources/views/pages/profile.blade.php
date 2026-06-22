@extends('layouts.app')

@php
    $person = $person ?? null;
    $displayName = trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) ?: $user->name;
    $words = preg_split('/\s+/', trim($displayName), -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) >= 2) {
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    } elseif (count($words) === 1) {
        $initials = mb_strtoupper(mb_substr($words[0], 0, min(2, mb_strlen($words[0]))));
    } else {
        $initials = mb_strtoupper(mb_substr($user->email ?? '?', 0, 1));
    }
    $roleLabel = $user->profile?->name ?? 'Usuario';
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Mi perfil" />

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">{{ $title ?? 'Mi perfil' }}</h3>

        @if (session('status'))
            <div
                class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        {{-- Resumen --}}
        <div class="mb-6 rounded-2xl border border-gray-200 p-5 dark:border-gray-800 lg:p-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col items-center w-full gap-6 xl:flex-row">
                    <div
                        class="flex h-[100px] w-[100px] shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-gray-100 text-2xl font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-800 dark:text-white/90">
                        {{ $initials }}
                    </div>
                    <div class="order-3 text-center xl:order-2 xl:text-left">
                        <h4 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90 md:text-xl">
                            {{ $displayName }}
                        </h4>
                        <div class="flex flex-col items-center gap-1 text-center xl:flex-row xl:gap-3 xl:text-left">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $roleLabel }}</p>
                            @if ($person?->address)
                                <div class="hidden h-3 w-px bg-gray-300 dark:bg-gray-700 xl:block"></div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($person->address, 80) }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Datos de cuenta y persona --}}
        <form method="post" action="{{ route('profile.update', [], false) }}" class="mb-6">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800 lg:p-6">
                <h4 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Datos de la cuenta</h4>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-7">
                    <div>
                        <label for="name" class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Nombre
                            en el sistema</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('name') border-red-500 @enderror" />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="email" class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Correo
                            de acceso</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                            autocomplete="username"
                            class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('email') border-red-500 @enderror" />
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($person)
                    <h4 class="mb-6 mt-8 text-lg font-semibold text-gray-800 dark:text-white/90">Datos de la persona</h4>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-7">
                        <div>
                            <label for="first_name"
                                class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Nombre</label>
                            <input type="text" name="first_name" id="first_name"
                                value="{{ old('first_name', $person->first_name) }}"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('first_name') border-red-500 @enderror" />
                            @error('first_name')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="last_name"
                                class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Apellido</label>
                            <input type="text" name="last_name" id="last_name"
                                value="{{ old('last_name', $person->last_name) }}"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('last_name') border-red-500 @enderror" />
                            @error('last_name')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="person_email"
                                class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Correo de
                                contacto</label>
                            <input type="email" name="person_email" id="person_email"
                                value="{{ old('person_email', $person->email) }}"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('person_email') border-red-500 @enderror" />
                            @error('person_email')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="phone"
                                class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Teléfono</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $person->phone) }}"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('phone') border-red-500 @enderror" />
                            @error('phone')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="document_number"
                                class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Documento</label>
                            <input type="text" name="document_number" id="document_number"
                                value="{{ old('document_number', $person->document_number) }}"
                                class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('document_number') border-red-500 @enderror" />
                            @error('document_number')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="lg:col-span-2">
                            <label for="address"
                                class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Dirección</label>
                            <textarea name="address" id="address" rows="2"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all @error('address') border-red-500 @enderror">{{ old('address', $person->address) }}</textarea>
                            @error('address')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Tu usuario no tiene una ficha de persona
                        vinculada; solo puedes editar nombre y correo de acceso.</p>
                @endif

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-[#FF4622] px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-[#C43B25]">
                        Guardar datos
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
