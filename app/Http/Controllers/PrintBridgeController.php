<?php

namespace App\Http\Controllers;

use App\Services\PrintBridgeQueue;
use App\Models\PrinterBranch;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class PrintBridgeController extends Controller
{
    public function worker(Request $request): View
    {
        if (! config('qz.enabled', true)) {
            abort(404, 'QZ Tray está desactivado en configuración.');
        }
        $branchId = (int) session('branch_id');
        abort_unless($branchId, 403, 'No hay sucursal activa.');
        $printer = trim((string) $request->query('printer', ''));
        $printerNames = PrinterBranch::query()->where('branch_id', $branchId)->where('status', 'E')->orderBy('name')->pluck('name')->all();
        if ($printer !== '' && ! $this->printerBelongsToBranch($branchId, $printer)) abort(400, 'Impresora no permitida para el puente.');

        return view('print-bridge.worker', [
            'title' => 'Puente de impresión QZ',
            'targetPrinter' => $printer,
            'printerNames' => $printerNames,
        ]);
    }

    public function pull(Request $request, PrintBridgeQueue $queue): JsonResponse
    {
        if (! config('qz.enabled', true)) {
            return response()->json(['job' => null, 'message' => 'deshabilitado'], 200);
        }
        $request->validate([
            'printer_name' => 'nullable|string|max:120',
        ]);
        $name = trim((string) $request->input('printer_name', ''));
        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json(['job' => null, 'message' => 'sin sucursal en sesión'], 200);
        }
        if ($name !== '' && ! $this->printerBelongsToBranch($branchId, $name)) return response()->json(['message' => 'impresora no permitida'], 422);
        $job = $queue->peek($branchId, $name ?: null);

        return response()->json(['job' => $job]);
    }

    public function ack(Request $request, PrintBridgeQueue $queue): JsonResponse
    {
        if (! config('qz.enabled', true)) {
            return response()->json(['success' => false, 'message' => 'deshabilitado'], 200);
        }
        $request->validate([
            'printer_name' => 'nullable|string|max:120',
            'job_id' => 'required|string|max:120',
        ]);
        $name = trim((string) $request->input('printer_name', ''));
        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'sin sucursal en sesión'], 200);
        }
        if ($name === '' || ! $this->printerBelongsToBranch($branchId, $name)) return response()->json(['success' => false, 'message' => 'impresora no permitida'], 422);
        $jobId = trim((string) $request->input('job_id'));
        if ($jobId === '') {
            return response()->json(['success' => false, 'message' => 'job_id inválido'], 422);
        }

        $queue->ack($branchId, $name, $jobId);
        // Idempotente: devolver éxito incluso si el trabajo ya no existe.
        // El objetivo se logró: el trabajo no está en la cola.

        return response()->json(['success' => true]);
    }

    public function fail(Request $request, PrintBridgeQueue $queue): JsonResponse
    {
        $validated = $request->validate([
            'printer_name' => 'nullable|string|max:120',
            'job_id' => 'required|string|max:120',
            'error' => 'nullable|string|max:1000',
        ]);
        $name = trim((string) ($validated['printer_name'] ?? ''));
        $branchId = (int) session('branch_id');
        if (! $branchId || $name === '' || ! $this->printerBelongsToBranch($branchId, $name)) {
            return response()->json(['success' => false, 'message' => 'Solicitud no válida'], 422);
        }

        $queue->fail($branchId, $name, (string) $validated['job_id'], (string) ($validated['error'] ?? 'Error de impresión en QZ'));

        return response()->json(['success' => true]);
    }

    public function retry(Request $request, int $job, PrintBridgeQueue $queue)
    {
        $branchId = (int) session('branch_id');
        if (! $branchId || ! $queue->retry($branchId, $job)) {
            return back()->with('error', 'No se pudo reenviar el trabajo de impresión.');
        }

        return back()->with('status', 'Trabajo reenviado a la estación de impresión.');
    }

    public function destroy(Request $request, int $job, PrintBridgeQueue $queue)
    {
        $branchId = (int) session('branch_id');
        if (! $branchId || ! $queue->discard($branchId, $job)) {
            return back()->with('error', 'No se pudo borrar el trabajo de impresión.');
        }

        return back()->with('status', 'Trabajo de impresión borrado correctamente.');
    }

    private function printerBelongsToBranch(int $branchId, string $name): bool
    {
        return PrinterBranch::query()->where('branch_id', $branchId)->where('status', 'E')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])->exists();
    }
}
