<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommandTicketsMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $module = DB::table('modules')->where('name', 'Pedidos')->first();
        if (! $module) return;

        $view = DB::table('views')->where('name', 'Comandas')->first();
        $viewId = $view?->id ?? DB::table('views')->insertGetId(['name'=>'Comandas','abbreviation'=>'CMD','status'=>1,'created_at'=>$now,'updated_at'=>$now]);
        $menu = DB::table('menu_option')->where('action', 'command-tickets.index')->whereNull('deleted_at')->first();
        $menuId = $menu?->id ?? DB::table('menu_option')->insertGetId(['name'=>'Comandas','icon'=>'ri-file-list-3-line','action'=>'command-tickets.index','view_id'=>$viewId,'module_id'=>$module->id,'status'=>1,'quick_access'=>false,'created_at'=>$now,'updated_at'=>$now]);
        $operation = DB::table('operations')->where('action', 'command-tickets.reprint')->whereNull('deleted_at')->first();
        $operationId = $operation?->id ?? DB::table('operations')->insertGetId(['name'=>'Reimprimir','icon'=>'ri-printer-line','action'=>'command-tickets.reprint','view_id'=>$viewId,'view_id_action'=>null,'color'=>'#2563EB','status'=>1,'type'=>'R','created_at'=>$now,'updated_at'=>$now]);

        $branches = DB::table('branches')->whereNull('deleted_at')->pluck('id');
        $profiles = DB::table('profiles')->pluck('id');
        foreach ($branches as $branchId) {
            DB::table('view_branch')->updateOrInsert(['view_id'=>$viewId,'branch_id'=>$branchId], ['deleted_at'=>null,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('branch_operation')->updateOrInsert(['operation_id'=>$operationId,'branch_id'=>$branchId], ['status'=>1,'deleted_at'=>null,'created_at'=>$now,'updated_at'=>$now]);
            foreach ($profiles as $profileId) {
                $permission = DB::table('user_permission')->where(['profile_id'=>$profileId,'branch_id'=>$branchId,'menu_option_id'=>$menuId])->first();
                if ($permission) DB::table('user_permission')->where('id',$permission->id)->update(['status'=>1,'deleted_at'=>null,'updated_at'=>$now]);
                else DB::table('user_permission')->insert(['id'=>(string)Str::uuid(),'name'=>'Comandas','profile_id'=>$profileId,'menu_option_id'=>$menuId,'branch_id'=>$branchId,'status'=>1,'created_at'=>$now,'updated_at'=>$now]);
                DB::table('operation_profile_branch')->updateOrInsert(['operation_id'=>$operationId,'profile_id'=>$profileId,'branch_id'=>$branchId], ['status'=>1,'deleted_at'=>null,'created_at'=>$now,'updated_at'=>$now]);
            }
        }
        Cache::forget('sidebar_menu');
    }
}
