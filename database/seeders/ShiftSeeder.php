<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;
use App\Models\Branch;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = Branch::first()->id ?? 2;

        // Turno Mañana
        Shift::create([
            'name'         => 'Mañana',
            'abbreviation' => 'TM',
            'start_time'   => '08:00:00',
            'end_time'     => '14:00:00',
            'branch_id'    => $branchId,
        ]);

        // Turno Tarde
        Shift::create([
            'name'         => 'Tarde',
            'abbreviation' => 'TT',
            'start_time'   => '14:00:00',
            'end_time'     => '20:00:00',
            'branch_id'    => $branchId,
        ]);
    }
}