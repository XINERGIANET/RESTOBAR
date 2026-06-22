<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\MenuHelper;
use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Profile;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('pages.auth.signin', ['title' => 'Login']);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();
            $branchId = $user?->person?->branch_id;
            $profileId = $user?->profile_id;
            $request->session()->put('branch_id', $branchId);
            $request->session()->put('profile_id', $profileId);
            $person = $user->person; 

            $request->session()->put('user_id', $user->id);
            $request->session()->put('user_name', $user->name);
            $request->session()->forget('cash_register_id');
            $request->session()->forget('cash_register_name');
            $request->session()->forget('cash_register_number');

            if ($person) {
                $request->session()->put('person_id', $person->id);             
                $fullName = $person->first_name . ' ' . $person->last_name;
                $request->session()->put('person_fullname', $fullName);
                $request->session()->put('branch_id', $person->branch_id);

                $shifts = Shift::where('branch_id', $person->branch_id)->get();
                $currentTime = now()->format('H:i:s');
                $assignedShift = $shifts->filter(function ($shift) use ($currentTime) {
                    return $currentTime >= $shift->start_time && $currentTime <= $shift->end_time;
                })->first();
                if (!$assignedShift && $shifts->count() === 1) {
                    $assignedShift = $shifts->first();
                }
                if ($assignedShift) {
                    $request->session()->put('shift_id', $assignedShift->id);
                    $shiftSnapshot = [
                        'name' => $assignedShift->name,
                        'start_time' => $assignedShift->start_time,
                        'end_time'   => $assignedShift->end_time
                    ];
                    $request->session()->put('shift_snapshot', $shiftSnapshot);
                }

                $hasCashRegisters = CashRegister::query()
                    ->where('branch_id', $person->branch_id)
                    ->exists();

                $request->session()->put('force_cash_register_modal', $hasCashRegisters && ! current_user_is_mozo());
            }

            // Redirección según perfil:
            // - Mozo: SIEMPRE a Salones de Pedidos (ignorar URL "intended" y vista por defecto)
            // - Otros: dashboard; si el perfil tiene default_view_id, se añade ?view_id= al destino por defecto
            if ($profileId) {
                $mozoProfileId = Profile::mozoProfileId();
                if ($mozoProfileId && (int) $profileId === (int) $mozoProfileId) {
                    return redirect()->route('orders.index');
                }
            }

            $defaultViewId = null;
            if ($profileId && $branchId) {
                $rawViewId = Profile::query()
                    ->whereNull('deleted_at')
                    ->whereKey($profileId)
                    ->value('default_view_id');
                $defaultViewId = MenuHelper::sanitizeProfileDefaultViewForLogin(
                    (int) $profileId,
                    (int) $branchId,
                    $rawViewId
                );
            }

            $dashboardPath = route('dashboard', array_filter(['view_id' => $defaultViewId]), false);
            $defaultUrl = $request->root().(str_starts_with($dashboardPath, '/') ? $dashboardPath : '/'.$dashboardPath);

            return redirect()->intended($defaultUrl);
        }

        return back()->withErrors([
            'name' => 'Credenciales invalidas.',
        ])->onlyInput('name');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', ['logout' => '1'])
            ->with('status', 'Sesion cerrada.');
    }
}
