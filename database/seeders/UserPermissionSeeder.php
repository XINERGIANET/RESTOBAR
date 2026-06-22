<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $profileId = DB::table('profiles')->value('id') ?? 1;
        $branchId = DB::table('branches')->value('id') ?? 1;

        $menuOptions = DB::table('menu_option')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->get();

        if ($menuOptions->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($menuOptions as $menuOption) {
            $existing = DB::table('user_permission')
                ->where('profile_id', $profileId)
                ->where('branch_id', $branchId)
                ->where('menu_option_id', $menuOption->id)
                ->first();

            if ($existing) {
                if (!empty($existing->deleted_at)) {
                    DB::table('user_permission')
                        ->where('id', $existing->id)
                        ->update([
                            'deleted_at' => null,
                            'name' => $menuOption->name,
                            'status' => true,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('user_permission')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $menuOption->name,
                            'status' => true,
                            'updated_at' => $now,
                        ]);
                }

                continue;
            }

            DB::table('user_permission')->insert([
                'id' => (string) Str::uuid(),
                'name' => $menuOption->name,
                'profile_id' => $profileId,
                'menu_option_id' => $menuOption->id,
                'branch_id' => $branchId,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
