<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\MenuOption;
use App\Models\CashRegister;
use App\Models\Profile;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('helpers.php');

        // Sidebar colapsado para Mozo: isMozo y hasMultipleModules
        View::composer('layouts.app', function ($view) {
            $isMozo = false;
            $hasMultipleModules = false;
            if (Auth::check()) {
                $profileId = session('profile_id') ?? Auth::user()?->profile_id;
                $resolved = $profileId !== null && $profileId !== '' ? (int) $profileId : null;
                $isMozo = Profile::userHasMozoProfile($resolved);
                $hasMultipleModules = MenuOption::where('status', 1)->pluck('module_id')->unique()->count() > 1;
            }
            $view->with(compact('isMozo', 'hasMultipleModules'));
        });

        // Compartir quickOptions con el header y el sidebar
        View::composer(['layouts.app-header', 'layouts.sidebar'], function ($view) {
            $quickOptions = MenuOption::where('status', 1)
                ->where('quick_access', 1)
                ->orderBy('id', 'asc')
                ->get();
            
            $view->with('quickOptions', $quickOptions);
        });

        View::composer('*', function ($view) {
            if (Auth::check()) {
                $isMozoProfile = current_user_is_mozo();
                $query = CashRegister::where('status', '1')->orderBy('number', 'asc');
                $branchId = session('branch_id');
                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }
                $cajas = $query->get();
                $currentCajaId = effective_cash_register_id($branchId ? (int) $branchId : null);
                $currentCaja = $currentCajaId ? $cajas->firstWhere('id', $currentCajaId) : null;

                if (! $currentCaja && session()->has('cash_register_id')) {
                    session()->forget(['cash_register_id', 'cash_register_name', 'cash_register_number']);
                }

                $cashSelectionRequired = $isMozoProfile
                    ? false
                    : cash_register_selection_required($branchId ? (int) $branchId : null);
                $forceCashRegisterModal = $isMozoProfile
                    ? false
                    : ($cashSelectionRequired || (bool) session('force_cash_register_modal', false));

                $view->with([
                    'cashRegisters' => $cajas,
                    'selectedCashRegister' => $currentCaja,
                    'cashSelectionRequired' => $cashSelectionRequired,
                    'forceCashRegisterModal' => $forceCashRegisterModal,
                    'cashRegisterSelectionEnabled' => ! $isMozoProfile,
                ]);
            }
        });
    }
}
