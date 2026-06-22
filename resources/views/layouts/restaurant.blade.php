<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Xinergia Restaurante' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#f6efe7] text-[#2b1c16] font-manrope antialiased">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-36 left-[-10%] h-[420px] w-[420px] rounded-full bg-[radial-gradient(circle_at_center,#f7c37f,transparent_70%)] opacity-70 blur-3xl"></div>
        <div class="absolute top-24 right-[-8%] h-[360px] w-[360px] rounded-full bg-[radial-gradient(circle_at_center,#f3a06b,transparent_68%)] opacity-60 blur-3xl"></div>
        <div class="absolute bottom-[-12%] left-[10%] h-[440px] w-[440px] rounded-full bg-[radial-gradient(circle_at_center,#d6a173,transparent_70%)] opacity-40 blur-3xl"></div>
        <div class="absolute inset-0 grain pointer-events-none"></div>
    </div>

    <x-common.loading-overlay/>

    @include('restaurant.partials.nav')

    <main class="relative">
        @yield('content')
    </main>

    @include('restaurant.partials.footer')

    @stack('scripts')
</body>

</html>
