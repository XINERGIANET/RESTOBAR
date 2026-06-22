<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QzTraySigningService
{
    private bool $pairLookupDone = false;

    /** @var array{cert: string, key: string, source: string}|null */
    private ?array $activePair = null;

    public function isConfigured(): bool
    {
        return $this->getActivePair() !== null;
    }

    public function certificateContents(): ?string
    {
        $pair = $this->getActivePair();

        return $pair['cert'] ?? null;
    }

    /**
     * Certificado para un par explícito (sin mezclar con el otro).
     * $pair: primary | secondary
     */
    public function certificateContentsForPair(string $pair): ?string
    {
        $resolved = $this->resolveStrictPair($pair);
        if ($resolved === null) {
            return null;
        }

        return $resolved['cert'] ?? null;
    }

    /**
     * Firma usando solo el par indicado (primary o secondary).
     */
    public function signForPair(string $pair, string $request): ?string
    {
        $resolved = $this->resolveStrictPair($pair);
        if ($resolved === null) {
            return null;
        }

        $key = openssl_pkey_get_private($resolved['key']);
        if ($key === false) {
            return null;
        }

        $signature = '';
        $ok = openssl_sign($request, $signature, $key, $this->opensslAlgorithmFromConfig());

        if (! $ok) {
            return null;
        }

        return base64_encode($signature);
    }

    public function sign(string $request): ?string
    {
        $pair = $this->getActivePair();
        if ($pair === null) {
            return null;
        }

        $key = openssl_pkey_get_private($pair['key']);
        if ($key === false) {
            return null;
        }

        $signature = '';
        $ok = openssl_sign($request, $signature, $key, $this->opensslAlgorithmFromConfig());

        if (! $ok) {
            return null;
        }

        return base64_encode($signature);
    }

    /**
     * @return array{cert: string, key: string, source: string}|null
     */
    private function resolveStrictPair(string $pair): ?array
    {
        $p = strtolower(trim($pair));
        if ($p === 'secondary') {
            return $this->buildPairFromConfig(
                'qz.certificate_path_secondary',
                'qz.private_key_path_secondary',
                'secondary'
            );
        }
        if ($p === 'primary') {
            return $this->buildPairFromConfig(
                'qz.certificate_path',
                'qz.private_key_path',
                'primary'
            );
        }

        return null;
    }

    /**
     * @return array{cert: string, key: string, source: string}|null
     */
    private function getActivePair(): ?array
    {
        if ($this->pairLookupDone) {
            return $this->activePair;
        }
        $this->pairLookupDone = true;

        $primary = $this->buildPairFromConfig(
            'qz.certificate_path',
            'qz.private_key_path',
            'primary'
        );
        if ($primary !== null) {
            $this->activePair = $primary;

            return $this->activePair;
        }

        $secondary = $this->buildPairFromConfig(
            'qz.certificate_path_secondary',
            'qz.private_key_path_secondary',
            'secondary'
        );
        if ($secondary !== null) {
            Log::info('QZ Tray: el par principal (app/qz) no está disponible o no es válido; usando el par secundario (app/qz2).');
            $this->activePair = $secondary;

            return $this->activePair;
        }

        $this->activePair = null;

        return null;
    }

    /**
     * @return array{cert: string, key: string, source: string}|null
     */
    private function buildPairFromConfig(string $certConfigKey, string $keyConfigKey, string $source): ?array
    {
        $cert = $this->readMaterialFromConfigKey($certConfigKey);
        $key = $this->readMaterialFromConfigKey($keyConfigKey);
        if ($cert === null || $key === null) {
            return null;
        }

        $privateKey = openssl_pkey_get_private($key);
        if ($privateKey === false) {
            return null;
        }

        $probe = '';
        if (! openssl_sign('xinergia-qz-tray-probe', $probe, $privateKey, $this->opensslAlgorithmFromConfig())) {
            return null;
        }

        return [
            'cert' => $cert,
            'key' => $key,
            'source' => $source,
        ];
    }

    private function readMaterialFromConfigKey(string $configKey): ?string
    {
        $configured = (string) config($configKey, '');

        if (trim($configured) === '') {
            return null;
        }

        if (str_contains($configured, 'BEGIN ') || strlen($configured) > 500) {
            $content = trim($configured);

            return $content !== '' ? $content : null;
        }

        $path = $this->resolvePath($configured);
        if (! File::exists($path)) {
            return null;
        }

        $content = trim((string) File::get($path));

        return $content !== '' ? $content : null;
    }

    private function resolvePath(string $path): string
    {
        if (
            preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) ||
            str_starts_with($path, '/') ||
            str_starts_with($path, '\\\\')
        ) {
            return $path;
        }

        return base_path($path);
    }

    private function opensslAlgorithmFromConfig(): int
    {
        $algo = strtoupper((string) config('qz.signature_algorithm', 'SHA512'));
        if ($algo === 'SHA512') {
            return OPENSSL_ALGO_SHA512;
        }
        if ($algo === 'SHA384') {
            return OPENSSL_ALGO_SHA384;
        }
        if ($algo === 'SHA256') {
            return OPENSSL_ALGO_SHA256;
        }

        return OPENSSL_ALGO_SHA1;
    }
}
