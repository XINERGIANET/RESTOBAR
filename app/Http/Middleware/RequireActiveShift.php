<?php

namespace App\Http\Middleware;

use App\Models\CashShiftRelation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveShift
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = session('branch_id');

        $hasActiveShift = CashShiftRelation::where('branch_id', $branchId)
            ->where('status', '1')
            ->exists();

        if (!$hasActiveShift) {
            $message = 'No hay un turno activo para esta sucursal. Realice una Apertura de Caja primero.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()->back()
                ->with('error', $message)
                ->withInput();
        }

        return $next($request);
    }
}
