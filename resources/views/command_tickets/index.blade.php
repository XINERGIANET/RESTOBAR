@extends('layouts.app')

@section('content')
@php
    $commandPreviews = $jobs->getCollection()->mapWithKeys(fn ($job) => [(string) $job->id => [
        'title' => 'Comanda #'.$job->id,
        'meta' => ($job->created_at?->format('d/m/Y H:i:s') ?? '').' · '.$job->printer_name,
        'content' => $job->readablePayload(),
    ]])->all();
@endphp
<script>
window.commandTicketsPage = () => ({
    open: false, title: '', meta: '', content: '', previews: @js($commandPreviews),
    editOpen: false, editAction: '', editDate: '',
    updateDateUrl: @js(route('command-tickets.update-date', ['job' => '__JOB__'])),
    show(jobId) {
        const item = this.previews[String(jobId)];
        if (!item) return;
        this.title = item.title; this.meta = item.meta; this.content = item.content; this.open = true;
    },
    edit(jobId, date) {
        this.editAction = this.updateDateUrl.replace('__JOB__', String(jobId));
        this.editDate = date;
        this.editOpen = true;
    }
});
</script>
<div x-data="window.commandTicketsPage()">
    <x-common.page-breadcrumb pageTitle="Comandas" />

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-5">
        @foreach([
            ['printed', 'Impresas', 'ri-checkbox-circle-line', 'text-emerald-600', 'bg-emerald-50'],
            ['pending', 'Pendientes', 'ri-time-line', 'text-amber-600', 'bg-amber-50'],
            ['processing', 'Procesando', 'ri-loader-4-line', 'text-blue-600', 'bg-blue-50'],
            ['failed', 'Con error', 'ri-error-warning-line', 'text-red-600', 'bg-red-50'],
        ] as [$key, $label, $icon, $color, $bg])
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $bg }} {{ $color }}"><i class="{{ $icon }} text-xl"></i></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">{{ $label }}</p><p class="text-2xl font-bold text-gray-900">{{ (int) ($counts[$key] ?? 0) }}</p></div>
                </div>
            </div>
        @endforeach
    </div>

    <x-common.component-card title="Historial de comandas" desc="Consulta comandas impresas y no impresas, revisa su contenido o vuelve a imprimirlas.">
        <form method="GET" class="mb-5 grid gap-3 md:grid-cols-2 xl:grid-cols-6 xl:items-end">
            <div class="xl:col-span-2"><label class="mb-1 block text-xs font-medium text-gray-600">Impresora</label><input name="search" value="{{ $search }}" placeholder="Buscar impresora" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"></div>
            <div><label class="mb-1 block text-xs font-medium text-gray-600">Estado</label><select name="status" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"><option value="">Todos</option>@foreach(['printed'=>'Impresa','pending'=>'Pendiente','processing'=>'Procesando','failed'=>'Con error'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-xs font-medium text-gray-600">Desde</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"></div>
            <div><label class="mb-1 block text-xs font-medium text-gray-600">Hasta</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"></div>
            <div class="flex gap-2"><button class="h-11 flex-1 rounded-lg bg-[#124731] px-4 text-sm font-semibold text-white"><i class="ri-search-line mr-1"></i>Buscar</button><a href="{{ route('command-tickets.index') }}" class="flex h-11 items-center rounded-lg border border-gray-300 px-3 text-gray-600"><i class="ri-refresh-line"></i></a></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full min-w-[950px] text-sm">
                <thead class="bg-[#124731] text-white"><tr><th class="px-4 py-3 text-left">Fecha</th><th class="px-4 py-3 text-left">Impresora</th><th class="px-4 py-3 text-center">Estado</th><th class="px-4 py-3 text-center">Intentos</th><th class="px-4 py-3 text-left">Error</th><th class="px-4 py-3 text-center">Acciones</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($jobs as $job)
                        @php
                            $state = ['printed'=>['Impresa','bg-emerald-100 text-emerald-700'],'pending'=>['Pendiente','bg-amber-100 text-amber-700'],'processing'=>['Procesando','bg-blue-100 text-blue-700'],'failed'=>['Con error','bg-red-100 text-red-700']][$job->status] ?? [$job->status,'bg-gray-100 text-gray-700'];
                        @endphp
                        <tr class="hover:bg-gray-50"><td class="px-4 py-3 whitespace-nowrap"><div class="font-medium text-gray-800">{{ $job->created_at?->format('d/m/Y') }}</div><div class="text-xs text-gray-500">{{ $job->created_at?->format('H:i:s') }}</div></td><td class="px-4 py-3 font-semibold">{{ $job->printer_name }}</td><td class="px-4 py-3 text-center"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $state[1] }}">{{ $state[0] }}</span></td><td class="px-4 py-3 text-center">{{ $job->attempts }}</td><td class="max-w-[280px] truncate px-4 py-3 text-gray-500" title="{{ $job->last_error }}">{{ $job->last_error ?: '—' }}</td><td class="px-4 py-3"><div class="flex justify-center gap-2"><button type="button" @click="show({{ $job->id }})" class="rounded-lg border border-gray-300 px-3 py-2 font-semibold text-gray-700 hover:bg-gray-100"><i class="ri-eye-line"></i> Ver</button><button type="button" data-command-date="{{ $job->created_at?->format('Y-m-d\\TH:i') }}" @click="edit({{ $job->id }}, $el.dataset.commandDate)" class="rounded-lg bg-amber-500 px-3 py-2 font-semibold text-white hover:bg-amber-600"><i class="ri-calendar-edit-line"></i> Fecha</button><form method="POST" action="{{ route('command-tickets.reprint', $job->id) }}" class="js-swal-delete" data-swal-title="¿Volver a imprimir?" data-swal-text="La comanda se enviará nuevamente a {{ $job->printer_name }}." data-swal-icon="question" data-swal-confirm="Sí, imprimir">@csrf<button class="rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700"><i class="ri-printer-line"></i> Reimprimir</button></form></div></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500"><i class="ri-file-list-3-line mb-2 block text-4xl text-gray-300"></i>No hay comandas para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $jobs->links() }}</div>
    </x-common.component-card>

    <div x-show="open" x-cloak @keydown.escape.window="open=false" class="fixed inset-0 z-[1000001] flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/50" @click="open=false"></div><div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"><div class="flex justify-between border-b px-5 py-4"><div><h3 class="font-bold" x-text="title"></h3><p class="text-xs text-gray-500" x-text="meta"></p></div><button @click="open=false" class="h-9 w-9 rounded-full bg-gray-100"><i class="ri-close-line"></i></button></div><div class="overflow-y-auto p-5"><pre class="whitespace-pre-wrap rounded-xl bg-gray-950 p-5 font-mono text-sm leading-6 text-white" x-text="content"></pre></div></div></div>

    <div x-show="editOpen" x-cloak @keydown.escape.window="editOpen=false" class="fixed inset-0 z-[1000001] flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/50" @click="editOpen=false"></div><form method="POST" :action="editAction" class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">@csrf @method('PATCH')<div class="mb-5 flex items-start justify-between"><div><h3 class="text-lg font-bold text-gray-900">Editar fecha de la comanda</h3><p class="mt-1 text-sm text-gray-500">Esta fecha también se actualizará dentro de la impresión.</p></div><button type="button" @click="editOpen=false" class="h-9 w-9 rounded-full bg-gray-100"><i class="ri-close-line"></i></button></div><label class="mb-1.5 block text-sm font-semibold text-gray-700">Nueva fecha y hora</label><input type="datetime-local" name="command_date" x-model="editDate" required class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-[#124731] focus:outline-none"><div class="mt-6 flex justify-end gap-2"><button type="button" @click="editOpen=false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700">Cancelar</button><button type="submit" class="rounded-lg bg-[#124731] px-4 py-2.5 text-sm font-semibold text-white"><i class="ri-save-line mr-1"></i>Guardar fecha</button></div></form></div>
</div>
@endsection
