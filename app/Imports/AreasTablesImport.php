<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

// ─── Hoja 1: Áreas ───────────────────────────────────────────────────────────
class AreasSheet implements ToCollection, WithStartRow
{
    private int $branchId;
    public array $errors  = [];
    public int $imported  = 0;
    public int $updated   = 0;

    public function __construct(int $branchId)
    {
        $this->branchId = $branchId;
    }

    public function startRow(): int { return 2; }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $values = $row->toArray();

            if (empty(array_filter($values, fn($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $nombre = isset($values[0]) ? trim((string) $values[0]) : '';

            if ($nombre === '') {
                $this->errors[] = "[Áreas] Fila {$rowNum}: El campo 'nombre_area' es requerido.";
                continue;
            }

            try {
                \DB::beginTransaction();

                $area = Area::where('branch_id', $this->branchId)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($nombre)])
                    ->first();

                if ($area) {
                    $this->updated++;
                } else {
                    Area::create([
                        'name'      => $nombre,
                        'branch_id' => $this->branchId,
                    ]);
                    $this->imported++;
                }

                \DB::commit();
            } catch (\Throwable $e) {
                try { \DB::rollBack(); } catch (\Throwable) {}
                $this->errors[] = "[Áreas] Fila {$rowNum}: " . $e->getMessage();
            }
        }
    }
}

// ─── Hoja 2: Mesas ───────────────────────────────────────────────────────────
class TablesSheet implements ToCollection, WithStartRow
{
    private int $branchId;
    public array $errors = [];
    public int $imported = 0;
    public int $updated  = 0;

    public function __construct(int $branchId)
    {
        $this->branchId = $branchId;
    }

    public function startRow(): int { return 2; }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $values = $row->toArray();

            if (empty(array_filter($values, fn($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $nombre    = isset($values[0]) ? trim((string) $values[0]) : '';
            $capacidad = isset($values[1]) ? (int) $values[1] : 4;
            $areaName  = isset($values[2]) ? trim((string) $values[2]) : '';

            if ($nombre === '') {
                $this->errors[] = "[Mesas] Fila {$rowNum}: El campo 'nombre_mesa' es requerido.";
                continue;
            }
            if ($areaName === '') {
                $this->errors[] = "[Mesas] Fila {$rowNum}: El campo 'nombre_area' es requerido.";
                continue;
            }

            // Cada fila en su propia transacción para evitar que un fallo aborte el batch
            try {
                \DB::beginTransaction();

                // Buscar área en esta sucursal
                $area = Area::where('branch_id', $this->branchId)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($areaName)])
                    ->first();

                if (!$area) {
                    \DB::rollBack();
                    $this->errors[] = "[Mesas] Fila {$rowNum}: Área '{$areaName}' no encontrada en esta sucursal. Agrégala en la hoja Áreas.";
                    continue;
                }

                // Buscar mesa existente por nombre en el área
                $table = Table::where('branch_id', $this->branchId)
                    ->where('area_id', $area->id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($nombre)])
                    ->first();

                if ($table) {
                    $table->update(['capacity' => max(1, $capacidad)]);
                    $this->updated++;
                } else {
                    // 'status' es smallint en PostgreSQL — no usar string 'A'
                    Table::create([
                        'name'      => $nombre,
                        'capacity'  => max(1, $capacidad),
                        'area_id'   => $area->id,
                        'branch_id' => $this->branchId,
                        'situation' => 'libre',
                    ]);
                    $this->imported++;
                }

                \DB::commit();
            } catch (\Throwable $e) {
                try { \DB::rollBack(); } catch (\Throwable) {}
                $this->errors[] = "[Mesas] Fila {$rowNum}: " . $e->getMessage();
            }
        }
    }
}

// ─── Coordinador de múltiples hojas ──────────────────────────────────────────
class AreasTablesImport implements WithMultipleSheets
{
    private int $branchId;
    public AreasSheet  $areasSheet;
    public TablesSheet $tablesSheet;

    public function __construct(int $branchId)
    {
        $this->branchId    = $branchId;
        $this->areasSheet  = new AreasSheet($branchId);
        $this->tablesSheet = new TablesSheet($branchId);
    }

    public function sheets(): array
    {
        return [
            0 => $this->areasSheet,
            1 => $this->tablesSheet,
        ];
    }

    public function errors(): array
    {
        return array_merge($this->areasSheet->errors, $this->tablesSheet->errors);
    }

    public function imported(): int
    {
        return $this->areasSheet->imported + $this->tablesSheet->imported;
    }

    public function updated(): int
    {
        return $this->areasSheet->updated + $this->tablesSheet->updated;
    }
}
