<?php

namespace App\Support;

/**
 * Monto EN LETRAS (español, formato factura MX): "SETECIENTOS VEINTE MIL PESOS 00/100 M.N.".
 * Es un DATO que la plantilla-contrato puede pedir con `{{honorarios_letra}}`; la redacción legal
 * sigue siendo de la productora (frontera: CrewCare aporta el dato, no redacta). Cubre 0–999,999,999.
 */
class MoneyWords
{
    public static function pesos(float $amount, ?string $currency = 'MXN'): string
    {
        $amount   = round(abs($amount), 2);
        $entero   = (int) floor($amount);
        $centavos = (int) round(($amount - $entero) * 100);
        $moneda   = strtoupper((string) ($currency ?: 'MXN')) === 'USD' ? 'DÓLARES' : 'PESOS';

        return mb_strtoupper(self::entero($entero)) . ' ' . $moneda . ' '
            . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100 M.N.';
    }

    /** Entero a letras (0–999,999,999). Los grupos de millones y miles son de 1–999 → usa centenas(). */
    private static function entero(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        $out = '';
        if ($n >= 1000000) {
            $mill = intdiv($n, 1000000);
            $out .= $mill === 1 ? 'un millón' : self::centenas($mill) . ' millones';
            $n %= 1000000;
            if ($n) {
                $out .= ' ';
            }
        }
        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $out .= $miles === 1 ? 'mil' : self::centenas($miles) . ' mil';
            $n %= 1000;
            if ($n) {
                $out .= ' ';
            }
        }
        if ($n > 0) {
            $out .= self::centenas($n);
        }

        return trim($out);
    }

    /** 1–999 a letras. */
    private static function centenas(int $n): string
    {
        $u = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
            'veintisiete', 'veintiocho', 'veintinueve'];
        $d = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $c = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos',
            'setecientos', 'ochocientos', 'novecientos'];

        if ($n === 100) {
            return 'cien';
        }

        $out = '';
        $h = intdiv($n, 100);
        $r = $n % 100;
        if ($h) {
            $out .= $c[$h];
        }
        if ($r) {
            if ($out !== '') {
                $out .= ' ';
            }
            if ($r < 30) {
                $out .= $u[$r];
            } else {
                $t = intdiv($r, 10);
                $ur = $r % 10;
                $out .= $d[$t] . ($ur ? ' y ' . $u[$ur] : '');
            }
        }

        return $out;
    }
}
