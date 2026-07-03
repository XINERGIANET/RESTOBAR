<?php

namespace App\Services;

use App\Models\PrintJob;
use App\Models\PrinterBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrintBridgeQueue
{
    public function stationPrinterNames(): array
    {
        $names = array_merge(
            config('qz.secondary_first_printer_names', ['BARRA2']),
            config('qz.tertiary_first_printer_names', ['BARRA3'])
        );

        return array_values(array_unique(array_filter(array_map('trim', $names))));
    }

    public function shouldQueueToStation(PrinterBranch $printer, bool $remoteRequest = false): bool
    {
        if (! config('qz.enabled', true) || ! $this->isStationPrinterName((string) $printer->name)) {
            return false;
        }

        return $remoteRequest || ! filled((string) $printer->ip);
    }

    public function isStationPrinterName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return false;
        }

        foreach ($this->stationPrinterNames() as $allowed) {
            if ($normalized === mb_strtolower(trim($allowed))) {
                return true;
            }
        }

        return false;
    }

    public function push(int $branchId, string $printerName, string $escposRaw, string $kind = 'comanda'): PrintJob
    {
        return PrintJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'requested_by' => auth()->id(),
            'printer_name' => trim($printerName),
            'kind' => $kind,
            'payload_base64' => base64_encode($escposRaw),
            'status' => 'pending',
        ]);
    }

    /** Reserva un trabajo. Una reserva abandonada vuelve a estar disponible luego de 90 segundos. */
    public function peek(int $branchId, string $printerName): ?array
    {
        return DB::transaction(function () use ($branchId, $printerName) {
            $job = PrintJob::query()
                ->where('branch_id', $branchId)
                ->whereRaw('LOWER(TRIM(printer_name)) = ?', [mb_strtolower(trim($printerName))])
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere(function ($stale) {
                            $stale->where('status', 'processing')
                                ->where('claimed_at', '<=', now()->subSeconds((int) config('print_bridge.claim_timeout_seconds', 90)));
                        });
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (! $job) {
                return null;
            }

            $job->update([
                'status' => 'processing',
                'claimed_at' => now(),
                'attempts' => $job->attempts + 1,
                'last_error' => null,
            ]);

            return [
                'id' => $job->uuid,
                'b64' => $job->payload_base64,
                'kind' => $job->kind,
                'attempts' => $job->attempts,
            ];
        }, 3);
    }

    public function ack(int $branchId, string $printerName, string $jobUuid): bool
    {
        return PrintJob::query()
            ->where('branch_id', $branchId)
            ->where('uuid', $jobUuid)
            ->whereRaw('LOWER(TRIM(printer_name)) = ?', [mb_strtolower(trim($printerName))])
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'printed',
                'printed_at' => now(),
                'claimed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]) > 0;
    }

    public function fail(int $branchId, string $printerName, string $jobUuid, string $error): bool
    {
        return PrintJob::query()
            ->where('branch_id', $branchId)
            ->where('uuid', $jobUuid)
            ->whereRaw('LOWER(TRIM(printer_name)) = ?', [mb_strtolower(trim($printerName))])
            ->where('status', 'processing')
            ->update([
                'status' => 'failed',
                'last_error' => Str::limit(trim($error), 1000, ''),
                'claimed_at' => null,
                'updated_at' => now(),
            ]) > 0;
    }

    public function retry(int $branchId, int $jobId): bool
    {
        return PrintJob::query()
            ->where('branch_id', $branchId)
            ->where('id', $jobId)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'claimed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]) > 0;
    }

    public function unresolvedForBranch(int $branchId, int $limit = 20)
    {
        return PrintJob::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending', 'processing', 'failed'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
