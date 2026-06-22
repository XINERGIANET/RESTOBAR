<button
    x-data="{ theme: 'light' }"
    x-init="document.documentElement.classList.remove('dark')"
    @click.prevent
    class="relative flex items-center justify-center text-gray-500 transition-colors bg-white border border-gray-200 rounded-full hover:text-dark-900 h-11 w-11 hover:bg-gray-100 hover:text-gray-700"
>
    <!-- Siempre mostrar icono de modo claro -->
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20" width="20" height="20">
        <path fill="currentColor" d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97Z" />
    </svg>
</button>
