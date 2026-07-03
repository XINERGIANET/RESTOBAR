<?php

namespace App\Support;

final class SpanishAmountInWords
{
    public static function soles(float $amount): string
    {
        $minorUnits = (int) round(abs($amount) * 100);
        $integer = intdiv($minorUnits, 100);
        $cents = $minorUnits % 100;
        $prefix = $amount < 0 ? 'MENOS ' : '';

        return $prefix.self::integer($integer, true)
            .' CON '.str_pad((string) $cents, 2, '0', STR_PAD_LEFT).'/100 SOLES';
    }

    private static function integer(int $number, bool $apocopate = false): string
    {
        if ($number === 0) {
            return 'CERO';
        }

        $parts = [];
        $millions = intdiv($number, 1_000_000);
        if ($millions > 0) {
            $parts[] = $millions === 1
                ? 'UN MILLÓN'
                : self::integer($millions, true).' MILLONES';
            $number %= 1_000_000;
        }

        $thousands = intdiv($number, 1_000);
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'MIL' : self::underThousand($thousands, true).' MIL';
            $number %= 1_000;
        }

        if ($number > 0) {
            $parts[] = self::underThousand($number, $apocopate);
        }

        return implode(' ', $parts);
    }

    private static function underThousand(int $number, bool $apocopate): string
    {
        $parts = [];
        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;

        if ($hundreds > 0) {
            $parts[] = match ($hundreds) {
                1 => $remainder === 0 ? 'CIEN' : 'CIENTO',
                2 => 'DOSCIENTOS',
                3 => 'TRESCIENTOS',
                4 => 'CUATROCIENTOS',
                5 => 'QUINIENTOS',
                6 => 'SEISCIENTOS',
                7 => 'SETECIENTOS',
                8 => 'OCHOCIENTOS',
                9 => 'NOVECIENTOS',
            };
        }

        if ($remainder > 0) {
            $parts[] = self::underHundred($remainder, $apocopate);
        }

        return implode(' ', $parts);
    }

    private static function underHundred(int $number, bool $apocopate): string
    {
        $special = [
            1 => $apocopate ? 'UN' : 'UNO', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO', 5 => 'CINCO',
            6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE', 10 => 'DIEZ', 11 => 'ONCE',
            12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE', 16 => 'DIECISÉIS',
            17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE', 20 => 'VEINTE',
            21 => $apocopate ? 'VEINTIÚN' : 'VEINTIUNO', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS',
            24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
        ];

        if (isset($special[$number])) {
            return $special[$number];
        }

        $tens = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
        $ten = intdiv($number, 10);
        $unit = $number % 10;

        return $tens[$ten].($unit > 0 ? ' Y '.$special[$unit] : '');
    }
}
