<div
  x-show="loaded"
  x-transition:leave="transition ease-in duration-500"
  x-transition:leave-start="opacity-100"
  x-transition:leave-end="opacity-0"
  x-init="setTimeout(() => loaded = false, 800)"
  class="fixed left-0 top-0 z-999999 flex h-screen w-screen flex-col items-center justify-center bg-white dark:bg-[#0c111d]"
>
  <div class="relative flex flex-col items-center gap-6">
    <!-- Spinner above -->
    <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-[#FF4622] border-t-transparent shadow-sm"></div>
    
    <!-- Logo -->
    <div class="relative flex items-center justify-center animate-pulse">
        <img src="/images/logo/Xinergia.png" alt="Xinergia Logo" class="h-12 w-auto dark:brightness-110" />
    </div>

    <!-- Loading Text (Optional but elegant) -->
    <div class="mt-2 text-xs font-medium tracking-[0.2em] uppercase text-gray-400 animate-pulse">
        Cargando...
    </div>
  </div>
</div>
