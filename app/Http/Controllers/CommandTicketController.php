<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\PrinterBranch;
use App\Services\PrintBridgeQueue;
use App\Services\ThermalNetworkPrintService;
use App\Support\InsensitiveSearch;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class CommandTicketController extends Controller
{
    public function index(Request $request)
    {
        $branchId = (int) effective_branch_id();
        abort_unless($branchId, 403, 'No hay una sucursal activa.');

        $status = trim((string) $request->input('status'));
        $search = trim((string) $request->input('search'));
        $perPage = in_array((int) $request->input('per_page', 20), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page', 20) : 20;

        $jobs = PrintJob::query()
            ->where('branch_id', $branchId)
            ->where('kind', 'comanda')
            ->when(in_array($status, ['pending', 'processing', 'printed', 'failed'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($search !== '', fn ($q) => InsensitiveSearch::whereInsensitiveLike($q, 'printer_name', $search))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $counts = PrintJob::query()->where('branch_id', $branchId)->where('kind', 'comanda')
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('command_tickets.index', compact('jobs', 'counts', 'status', 'search', 'perPage'));
    }

    public function updateDate(Request $request, int $job)
    {
        $validated = $request->validate([
            'command_date' => ['required', 'date_format:Y-m-d\\TH:i'],
        ], [
            'command_date.required' => 'Selecciona la nueva fecha y hora.',
            'command_date.date_format' => 'La fecha y hora no tienen un formato válido.',
        ]);

        $branchId = (int) effective_branch_id();
        $printJob = PrintJob::query()
            ->where('branch_id', $branchId)
            ->where('kind', 'comanda')
            ->findOrFail($job);
        $newDate = Carbon::createFromFormat('Y-m-d\\TH:i', $validated['command_date']);
        $payload = base64_decode((string) $printJob->payload_base64, true);

        if ($payload === false) {
            return back()->with('error', 'No se pudo leer el contenido de la comanda.');
        }

        $replacement = 'Fecha: '.$newDate->format('d/m/Y H:i:s');
        $updatedPayload = preg_replace(
            '/(^|\\n)Fecha(?:\\/Hora)?:\\s*[^\\r\\n]*/i',
            '$1'.$replacement,
            $payload,
            1,
            $replacements
        );

        if ($updatedPayload === null || $replacements === 0) {
            return back()->with('error', 'No se encontró la fecha dentro del contenido de la comanda.');
        }

        $printJob->timestamps = false;
        $printJob->forceFill([
            'payload_base64' => base64_encode($updatedPayload),
            'created_at' => $newDate,
        ])->save();

        return back()->with('status', 'Fecha de la comanda actualizada correctamente.');
    }

    public function reprint(int $job, PrintBridgeQueue $queue)
    {
        $branchId = (int) effective_branch_id();
        $printJob = PrintJob::query()->where('branch_id', $branchId)->where('kind', 'comanda')->findOrFail($job);
        $printer = PrinterBranch::query()->where('branch_id', $branchId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($printJob->printer_name))])->first();

        if (! $printer) {
            $queue->markFailed($branchId, $printJob->id, 'La impresora ya no está registrada en esta sucursal.');
            return back()->with('error', 'La impresora de esta comanda ya no está registrada.');
        }

        if ($queue->shouldQueueToStation($printer, ! filled((string) $printer->ip))) {
            $queue->requeue($branchId, $printJob->id);
            return back()->with('status', 'Comanda enviada nuevamente a la cola de impresión.');
        }

        $payload = base64_decode($printJob->payload_base64, true);
        if ($payload === false) return back()->with('error', 'El contenido de la comanda no es válido.');

        try {
            $service = app(ThermalNetworkPrintService::class);
            if (filled((string) $printer->ip)) {
                $service->sendRaw((string) $printer->ip, (int) config('local_network.thermal_port', 9100), $payload, (int) config('local_network.thermal_timeout_seconds', 4));
            } else {
                $service->sendRawToWindowsPrinter((string) $printer->name, $payload, (int) config('local_network.thermal_timeout_seconds', 4) + 4);
            }
            $queue->markPrinted($branchId, $printJob->id);
            return back()->with('status', 'Comanda reimpresa correctamente.');
        } catch (\Throwable $e) {
            $queue->markFailed($branchId, $printJob->id, $e->getMessage());
            return back()->with('error', 'No se pudo reimprimir la comanda: '.$e->getMessage());
        }
    }
}
