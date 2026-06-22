<?php

namespace App\Http\Controllers;

use App\Services\QzTraySigningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QzTrayController extends Controller
{
    private function enforceOriginIfNeeded(Request $request): void
    {
        if (!config('qz.production_lock', false)) {
            return;
        }

        // En local/staging no bloqueamos para no romper el desarrollo.
        if (!app()->environment('production')) {
            return;
        }

        $allowedList = config('qz.allowed_origins', []);
        if (!is_array($allowedList) || $allowedList === []) {
            return;
        }

        $origin = trim((string) $request->headers->get('origin', ''));
        if ($origin === '') {
            abort(403, 'Origin requerido para QZ.');
        }

        if (!in_array($origin, $allowedList, true)) {
            abort(403, 'Origin no permitido para QZ.');
        }
    }

    public function certificate(Request $request, QzTraySigningService $signing)
    {
        if (!config('qz.enabled', true)) {
            abort(503, 'QZ Tray deshabilitado.');
        }

        try {
            $pair = strtolower(trim((string) $request->query('pair', '')));
            if ($pair !== '' && ! in_array($pair, ['primary', 'secondary'], true)) {
                abort(400, 'Par de certificado QZ inválido.');
            }

            if ($pair === '') {
                $certificate = $signing->certificateContents();
            } else {
                $certificate = $signing->certificateContentsForPair($pair);
            }

            Log::debug('QZ Tray: entrega de certificado', [
                'pair' => $pair === '' ? 'auto' : $pair,
                'ip' => $request->ip(),
            ]);

            if ($certificate === null || trim($certificate) === '') {
                abort(503, 'Certificado QZ no encontrado.');
            }

            return response($certificate, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('QZ certificate error: ' . $e->getMessage());
            abort(500, 'No se pudo obtener el certificado de QZ.');
        }
    }

    public function sign(Request $request, QzTraySigningService $signing)
    {
        if (!config('qz.enabled', true)) {
            abort(503, 'QZ Tray deshabilitado.');
        }

        $this->enforceOriginIfNeeded($request);

        try {
            // Soporta GET ?request=... (qz-tray-init actual) y POST JSON {request: "..."} (ejemplo legado).
            $request->validate(['request' => 'required|string']);
            $payload = (string) $request->input('request');
            $pair = strtolower(trim((string) ($request->query('pair') ?? $request->input('pair') ?? '')));
            if ($pair !== '' && ! in_array($pair, ['primary', 'secondary'], true)) {
                abort(400, 'Par de certificado QZ inválido.');
            }

            if ($pair === '') {
                $signature = $signing->sign($payload);
            } else {
                $signature = $signing->signForPair($pair, $payload);
            }

            Log::info('QZ Tray: firma de solicitud', [
                'pair' => $pair === '' ? 'auto' : $pair,
                'ip' => $request->ip(),
            ]);

            if ($signature === null || trim($signature) === '') {
                abort(503, 'No se pudo firmar la solicitud de QZ.');
            }

            // El bundle qz-tray-init.js siempre firma por POST JSON y espera { "signature": "..." }.
            // Antes dependíamos de expectsJson(); en algunos navegadores/proxies el Accept no llega y
            // Laravel devolvía texto plano, rompía el parseo y QZ Tray caía al aviso de "Demo Cert".
            if ($request->isMethod('POST') || $request->expectsJson() || $request->isJson()) {
                return response()->json(['signature' => $signature]);
            }

            return response($signature, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('QZ sign error: ' . $e->getMessage());
            abort(500, 'No se pudo firmar la solicitud de QZ.');
        }
    }
}
