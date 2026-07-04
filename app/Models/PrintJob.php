<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'uuid',
        'branch_id',
        'requested_by',
        'printer_name',
        'kind',
        'payload_base64',
        'status',
        'attempts',
        'last_error',
        'claimed_at',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    /** Devuelve el contenido imprimible sin los comandos de control ESC/POS. */
    public function readablePayload(): string
    {
        $payload = base64_decode((string) $this->payload_base64, true);
        if ($payload === false || $payload === '') {
            return 'No se pudo leer el contenido de esta impresión.';
        }

        $payload = str_replace([
            "\x1B\x40", "\x1B\x74\x02",
            "\x1B\x45\x00", "\x1B\x45\x01",
            "\x1B\x21\x00", "\x1B\x21\x10",
            "\x1D\x56\x00", "\x1D\x56\x42\x10",
        ], '', $payload);

        $payload = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $payload) ?? $payload;
        $payload = str_replace(["\r\n", "\r"], "\n", $payload);

        return trim($payload) ?: 'La impresión no contiene texto visible.';
    }
}
