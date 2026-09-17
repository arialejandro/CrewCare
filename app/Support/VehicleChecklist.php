<?php

namespace App\Support;

use App\Models\VehicleCheckPoint;
use Illuminate\Support\Collection;

/**
 * Atributos del vehículo + evaluación de `applies_when` para derivar los puntos del checklist.
 *
 * ATRIBUTOS (el tipo propone, la unidad confirma). Llaves canónicas:
 *   powertrain (combustion|electric|hybrid) · has_cargo_box · seats · has_lpg_or_sanitary ·
 *   has_genset_or_heat_appliances · water_tank_liters · tows
 *
 * `applies_when` es una GRAMÁTICA CONTROLADA (autoría del catálogo, no del usuario), evaluada
 * SIN eval(): o un token booleano ("has_cargo_box", "tows"), o "attr OP valor" con
 * OP ∈ {!=,==,>=,<=,>,<}. NULL/'' = aplica siempre.
 */
class VehicleChecklist
{
    /** Powertrains válidos. */
    const POWERTRAINS = ['combustion', 'electric', 'hybrid'];

    /**
     * Llaves booleanas del perfil de atributos.
     *   tows      = esta unidad JALA algo (tractor/pickup con enganche).
     *   is_towed  = esta unidad ES remolcada (no se conduce: sin volante/cinturones/luces
     *               principales/rodaje propio; sus frenos y luces se revisan por REMOLQUE).
     */
    const BOOL_KEYS = ['has_cargo_box', 'has_lpg_or_sanitary', 'has_genset_or_heat_appliances', 'tows', 'is_towed'];

    /** Llaves enteras nulables. */
    const INT_KEYS = ['seats', 'water_tank_liters'];

    /**
     * Devuelve el mapa de atributos con TODAS las llaves canónicas presentes y tipadas.
     * powertrain default 'combustion'; booleanas default false; enteras default null.
     */
    public static function normalizeAttributes(array $attrs): array
    {
        $pt = strtolower(trim((string) ($attrs['powertrain'] ?? 'combustion')));
        if (! in_array($pt, self::POWERTRAINS, true)) {
            $pt = 'combustion';
        }

        $out = ['powertrain' => $pt];
        foreach (self::BOOL_KEYS as $k) {
            $out[$k] = ! empty($attrs[$k]) && $attrs[$k] !== '0' && $attrs[$k] !== 'false';
        }
        foreach (self::INT_KEYS as $k) {
            $v = $attrs[$k] ?? null;
            $out[$k] = ($v === null || $v === '') ? null : (int) $v;
        }
        return $out;
    }

    /**
     * ¿Aplica este punto a un vehículo con estos atributos?
     *
     * @param  string|null $expr   applies_when del punto
     * @param  array       $attrs  atributos YA normalizados
     */
    public static function applies(?string $expr, array $attrs): bool
    {
        $expr = trim((string) $expr);
        if ($expr === '') {
            return true; // aplica siempre
        }

        // Conjunción (AND): "a && b" → TODAS deben cumplirse. Se evalúa antes que || (precedencia).
        if (strpos($expr, '&&') !== false) {
            foreach (explode('&&', $expr) as $part) {
                if (! self::applies(trim($part), $attrs)) {
                    return false;
                }
            }
            return true;
        }

        // Disyunción (OR): "a || b" → CUALQUIERA cumple.
        if (strpos($expr, '||') !== false) {
            foreach (explode('||', $expr) as $part) {
                if (self::applies(trim($part), $attrs)) {
                    return true;
                }
            }
            return false;
        }

        // "attr OP valor"
        if (preg_match('/^([a-z_][a-z0-9_]*)\s*(!=|==|>=|<=|>|<)\s*(.+)$/i', $expr, $m)) {
            $key = $m[1];
            $op  = $m[2];
            $rhs = trim($m[3], " \t\"'");
            $lhs = array_key_exists($key, $attrs) ? $attrs[$key] : null;

            if (is_numeric($rhs)) {
                $l = self::toNumber($lhs);
                $r = (float) $rhs;
                switch ($op) {
                    case '!=': return $l != $r;
                    case '==': return $l == $r;
                    case '>=': return $l >= $r;
                    case '<=': return $l <= $r;
                    case '>':  return $l >  $r;
                    case '<':  return $l <  $r;
                }
                return false;
            }

            // Comparación textual (p. ej. powertrain != electric)
            $l = is_bool($lhs) ? ($lhs ? '1' : '0') : strtolower((string) $lhs);
            $r = strtolower($rhs);
            switch ($op) {
                case '!=': return $l !== $r;
                case '==': return $l === $r;
                // Los relacionales sobre texto no tienen sentido aquí → no aplica.
                default:   return false;
            }
        }

        // Token booleano suelto ("has_cargo_box").
        return ! empty($attrs[$expr] ?? null);
    }

    private static function toNumber($v): float
    {
        if (is_bool($v)) {
            return $v ? 1.0 : 0.0;
        }
        if ($v === null || $v === '') {
            return 0.0;
        }
        return (float) $v;
    }

    /**
     * Los puntos ACTIVOS del catálogo que le tocan a un vehículo con estos atributos,
     * ordenados por sort_order y luego código.
     *
     * @return Collection<int,VehicleCheckPoint>
     */
    public static function pointsFor(array $attrs): Collection
    {
        $attrs = self::normalizeAttributes($attrs);

        $applicable = VehicleCheckPoint::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->filter(function (VehicleCheckPoint $p) use ($attrs) {
                return self::applies($p->applies_when, $attrs);
            })
            ->values();

        return self::applySupersession($applicable);
    }

    /**
     * SUSTITUCIÓN — mismo mecanismo que el catálogo de herramientas (`tool_check_points.supersedes`):
     * un punto APLICABLE que sustituye a otro DESPLAZA al sustituido cuando ambos aplican (dice lo
     * mismo con más precisión; evita inflar el checklist). Sólo cuentan los `supersedes` de puntos
     * que YA pasaron applies(). Ejemplo: REM-005 (calzas del remolcado, is_towed) sustituye a
     * CAR-004 (cuñas de la caja, has_cargo_box) en un camper de vestuario (ambos true).
     *
     * DEFENSIVO: si la columna `supersedes` aún no existe, `$p->supersedes` es null → no-op.
     *
     * @param  Collection<int,VehicleCheckPoint> $applicable
     * @return Collection<int,VehicleCheckPoint>
     */
    private static function applySupersession(Collection $applicable): Collection
    {
        $superseded = $applicable
            ->pluck('supersedes')
            ->map(fn ($c) => trim((string) $c))
            ->filter(fn ($c) => $c !== '')
            ->unique()
            ->all();

        if (empty($superseded)) {
            return $applicable;
        }

        return $applicable
            ->reject(fn (VehicleCheckPoint $p) => in_array($p->code, $superseded, true))
            ->values();
    }
}
