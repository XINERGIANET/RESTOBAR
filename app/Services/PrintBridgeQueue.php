<?php

namespace App\Services;

use App\Models\PrinterBranch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrintBridgeQueue
{
    /**
     * Misma lista que BARRA2 / certificado secondary (app/qz2) en qz.php — sin variables nuevas.
     */
    public function stationPrinterNames(): array
    {
        $names = array_merge(
            config('qz.secondary_first_printer_names', ['BARRA2']),
            config('qz.tertiary_first_printer_names', ['BARRA3'])
        );

        return array_values(array_unique(array_filter(array_map(
            'trim',
            $names
        ))));
    }

    public function shouldQueueToStation(PrinterBranch $printer): bool
    {
        if (! config('qz.enabled', true)) {
            return false;
        }
        if (filled((string) $printer->ip)) {
            return false;
        }
        $n = mb_strtolower(trim($printer->name));
        foreach ($this->stationPrinterNames() as $t) {
            if ($n === mb_strtolower(trim($t))) {
                return true;
            }
        }

        return false;
    }

    public function isStationPrinterName(string $name): bool
    {
        $n = mb_strtolower(trim($name));
        if ($n === '') {
            return false;
        }
        foreach ($this->stationPrinterNames() as $t) {
            if ($n === mb_strtolower(trim($t))) {
                return true;
            }
        }

        return false;
    }

    public function key(int $branchId, string $printerName): string
    {
        $norm = mb_strtolower(trim($printerName));

        return 'print_br:' . $branchId . ':' . md5($norm);
    }

    public function push(int $branchId, string $printerName, string $escposRaw): void
    {
        $k = $this->key($branchId, $printerName);
        $lock = Cache::lock('lock:' . $k, 5);
        $lock->block(4, function () use ($k, $escposRaw, $branchId, $printerName) {
            $list = Cache::get($k, []);
            $list[] = [
                'id' => (string) Str::uuid(),
                'b64' => base64_encode($escposRaw),
                'at' => time(),
            ];
            $max = (int) config('print_bridge.max_queue_length', 200);
            if (count($list) > $max) {
                $list = array_slice($list, -$max);
            }
            $ttl = (int) config('print_bridge.cache_ttl_seconds', 600);
            Cache::put($k, $list, $ttl);
        });
        if (config('app.debug')) {
            Log::debug('Print bridge encolada', [
                'branch_id' => $branchId,
                'printer' => $printerName,
            ]);
        }
    }

    public function pop(int $branchId, string $printerName): ?array
    {
        $k = $this->key($branchId, $printerName);

        return Cache::lock('lock:' . $k, 5)->block(3, function () use ($k) {
            $list = Cache::get($k, []);
            if (empty($list) || ! is_array($list)) {
                return null;
            }
            $job = array_shift($list);
            $ttl = (int) config('print_bridge.cache_ttl_seconds', 600);
            Cache::put($k, $list, $ttl);

            return is_array($job) ? $job : null;
        });
    }

    public function peek(int $branchId, string $printerName): ?array
    {
        $k = $this->key($branchId, $printerName);

        return Cache::lock('lock:' . $k, 5)->block(3, function () use ($k) {
            $list = Cache::get($k, []);
            if (empty($list) || ! is_array($list)) {
                return null;
            }

            $job = reset($list);

            return is_array($job) ? $job : null;
        });
    }

    public function ack(int $branchId, string $printerName, string $jobId): bool
    {
        $k = $this->key($branchId, $printerName);

        return Cache::lock('lock:' . $k, 5)->block(3, function () use ($k, $jobId) {
            $list = Cache::get($k, []);
            if (empty($list) || ! is_array($list)) {
                return false;
            }
            $originalCount = count($list);
            $list = array_values(array_filter($list, function ($job) use ($jobId) {
                return ! (is_array($job) && isset($job['id']) && (string) $job['id'] === $jobId);
            }));
            if (count($list) === $originalCount) {
                return false;
            }
            $ttl = (int) config('print_bridge.cache_ttl_seconds', 600);
            Cache::put($k, $list, $ttl);

            return true;
        });
    }
}
