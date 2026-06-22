<?php

namespace App\Http\Controllers;

use App\Services\PrintBridgeQueue;
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
        $printer = trim((string) $request->query('printer', 'BARRA2')) ?: 'BARRA2';
        if (! app(PrintBridgeQueue::class)->isStationPrinterName($printer)) {
            abort(400, 'Impresora no permitida para el puente.');
        }

        return view('print-bridge.worker', [
            'title' => 'Puente BARRA2 (QZ)',
            'targetPrinter' => $printer,
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
        $name = trim((string) $request->input('printer_name', 'BARRA2')) ?: 'BARRA2';
        if (! $queue->isStationPrinterName($name)) {
            return response()->json(['message' => 'impresora no permitida'], 422);
        }
        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json(['job' => null, 'message' => 'sin sucursal en sesión'], 200);
        }
        $job = $queue->peek($branchId, $name);
        if ($job) {
            $job['printer_name'] = $name;
        }

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
        $name = trim((string) $request->input('printer_name', 'BARRA2')) ?: 'BARRA2';
        if (! $queue->isStationPrinterName($name)) {
            return response()->json(['success' => false, 'message' => 'impresora no permitida'], 422);
        }
        $branchId = (int) session('branch_id');
        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'sin sucursal en sesión'], 200);
        }
        $jobId = trim((string) $request->input('job_id'));
        if ($jobId === '') {
            return response()->json(['success' => false, 'message' => 'job_id inválido'], 422);
        }

        $queue->ack($branchId, $name, $jobId);
        // Idempotente: devolver éxito incluso si el trabajo ya no existe.
        // El objetivo se logró: el trabajo no está en la cola.

        return response()->json(['success' => true]);
    }
}
