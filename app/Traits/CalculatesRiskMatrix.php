<?php

namespace App\Traits;

/**
 * Trait CalculatesRiskMatrix — el SERVIDOR calcula la severidad, no el usuario.
 *
 * Matriz de riesgo 5×5 (Amazon MGM Studios) para los reportes de VALOR ÚNICO
 * (Acción Insegura, Condición Insegura, Accidente). Toma:
 *   - likelihood  (Probabilidad)  A–E
 *   - consequence (Consecuencia)  1–5
 * y sobrescribe silenciosamente `risk_level` en el evento `saving` usando la
 * MISMA rejilla que el Scouting (ScoutingReportController@riskRating). El valor
 * de la celda L/M/H/E se traduce a Bajo/Medio/Alto/Extremo.
 *
 * HOMOLOGADO con el Scouting (Amazon MGM): antes este trait INVERTÍA el modelo
 * mapeando A→1..E→5 y multiplicando por bandas (producto), de modo que un peligro
 * "Casi seguro" (A) salía bajo y uno "Raro" (E) salía extremo — BUG. Ahora el
 * cálculo REAL es por rejilla (grid lookup), idéntico al del Scouting, así que
 * A×5 → Extremo y E×5 → Alto, como manda el formulario oficial.
 *
 * Requiere que el modelo tenga columnas likelihood / consequence / risk_level.
 * Si faltan insumos válidos (o las columnas aún no existen porque el owner no
 * aplicó el SQL), NO toca risk_level → respeta el valor manual/existente
 * (compatibilidad hacia atrás y despliegue seguro).
 *
 * NOTA: el Scouting NO usa este trait — tiene su propio motor por-fila
 * (ScoutingReportController@riskRating, misma matriz Amazon MGM sobre arrays).
 */
trait CalculatesRiskMatrix
{
    /**
     * Enganche automático de Eloquent: static::boot{TraitName}() se invoca al bootear el modelo.
     */
    public static function bootCalculatesRiskMatrix()
    {
        static::saving(function ($model) {
            // (2026-07-13) OVERRIDE del Safety Manager: si hay un valor explícito en
            // override_risk_level, su CRITERIO EXPERTO gana sobre el cálculo automático.
            // isset() es false si la columna no existe (PROD sin SQL) → no rompe.
            if (isset($model->override_risk_level)
                && $model->override_risk_level !== null
                && $model->override_risk_level !== '') {
                $model->risk_level = $model->override_risk_level;
                return;
            }

            // isset() sobre un atributo ausente devuelve false → no-op seguro
            // cuando las columnas aún no existen en la BD.
            $likelihood = isset($model->likelihood) ? strtoupper((string) $model->likelihood) : '';
            $consequence = isset($model->consequence) ? (int) $model->consequence : 0;

            $level = self::riskLevelFromMatrix($likelihood, $consequence);
            if ($level === null) {
                return; // sin par válido: se respeta el risk_level existente
            }

            $model->risk_level = $level;
        });
    }

    /**
     * Cálculo REAL: rejilla Amazon MGM (idéntica a ScoutingReportController@riskRating).
     * Fila = likelihood (A–E), columna = consequence (1–5). Celda L/M/H/E → palabra.
     *
     * Etiquetas canónicas de los ejes:
     *   likelihood (Probabilidad):  A · Casi seguro | B · Probable | C · Moderado | D · Improbable | E · Raro
     *   consequence (Consecuencia): 1 · Insignificante | 2 · Menor | 3 · Moderado | 4 · Mayor | 5 · Catastrófico
     *
     * @param  string|null     $likelihood   A|B|C|D|E (case-insensitive)
     * @param  string|int|null $consequence  1..5
     * @return string|null  Bajo|Medio|Alto|Extremo, o null si el par es inválido
     */
    public static function riskLevelFromMatrix($likelihood, $consequence)
    {
        $rows = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
        $key = strtoupper((string) $likelihood);
        $c = (int) $consequence;

        if (!isset($rows[$key]) || $c < 1 || $c > 5) {
            return null;
        }

        // Filas A–E, columnas 1–5 (idénticas al PDF de Amazon MGM Studios /
        // ScoutingReportController@riskRating). Valores L/M/H/E.
        $matrix = [
            ['M', 'H', 'H', 'E', 'E'], // A (casi seguro)
            ['M', 'M', 'H', 'H', 'E'], // B (probable)
            ['L', 'M', 'M', 'H', 'E'], // C (moderado)
            ['L', 'M', 'M', 'H', 'H'], // D (improbable)
            ['L', 'L', 'M', 'M', 'H'], // E (raro)
        ];

        $cell = $matrix[$rows[$key]][$c - 1];

        $words = ['L' => 'Bajo', 'M' => 'Medio', 'H' => 'Alto', 'E' => 'Extremo'];

        return $words[$cell];
    }

    /**
     * @deprecated Producto de bandas 1–25 → nivel. Se conserva SÓLO por
     * compatibilidad de firma pública (sin llamadores externos conocidos). El
     * cálculo oficial ahora es por rejilla vía riskLevelFromMatrix(); NO usar
     * este método para nuevos reportes — no refleja la matriz Amazon MGM.
     *
     * @param  int  $score
     * @return string  Bajo|Medio|Alto|Extremo
     */
    public static function riskLevelFromScore($score)
    {
        if ($score <= 4) {
            return 'Bajo';
        }
        if ($score <= 9) {
            return 'Medio';
        }
        if ($score <= 16) {
            return 'Alto';
        }
        return 'Extremo';
    }
}
