<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Une una cadena de norma cruda ("NOM-029-STPS-2011", "29 CFR 1926.300(c)",
 * "#16 Pyrotechnics", "CSATF 19") contra el catálogo QUE YA EXISTE,
 * `safety_standards.regulation_code`. NO inventa filas: lo que no resuelve regresa
 * como pendiente (id null) para parquearlo en `catalog_pending_standards`.
 *
 * Estrategia DELIBERADAMENTE CONSERVADORA — un mapeo equivocado ancla una
 * herramienta a la norma incorrecta:
 *   1) exact   — igualdad literal contra regulation_code.
 *   2) csatf   — solo en el bucket csatf: "#NN ..." / "CSATF NN" → "Bulletin #NN".
 *                Los ADDENDA ("#8A", "#36A") y las guías NO se mapean a su boletín
 *                base: quedan pendientes. La negative-lookahead corta el "8A".
 *   3) paren   — quita UNA anotación final entre paréntesis y reintenta EXACTO
 *                ("NOM-002-STPS-2010 (incendios)" → "NOM-002-STPS-2010").
 * No hay coincidencia difusa por normalización: en la medición no aportó ni una
 * unión y sí abría la puerta a anclar mal. Lo que no cae en 1-3 es pendiente.
 */
class StandardCodeResolver
{
    /**
     * Mapeos ADJUDICADOS por el panel adversarial del Paso 3 (≥2/3 de acuerdo, 3
     * revisores con lentes distintas: OSHA, NOM/CSATF, escéptico). Se revisan ANTES
     * que el algoritmo, por la cadena CRUDA exacta. Son rescates que el matcher
     * conservador dejó pendientes pero que el panel confirmó como la MISMA norma:
     *   - 1910.178(q)(7): sub-inciso que colapsa POR DEBAJO del grano del catálogo
     *     (la sección 1910.178 sí existe verbatim). 3/3.
     *   - SB 132: duplicado con anotación de año/prefijo; misma sección §§9150-9161,
     *     mismo bill SB 132. 3/3.
     * NO incluye la truncación sección→Subpart (1926.451→"29 CFR 1926 Subpart L"):
     * el panel la RECHAZÓ (A sí / B lean-no / C no) por SOBRE-ALCANCE — el catálogo
     * guarda a propósito los dos granos (sección y Subpart) como filas distintas, así
     * que subir una sección a su Subpart afirma una equivalencia que el catálogo no
     * sostiene. Queda pendiente, como decisión de granularidad del owner.
     */
    private const ADJUDICATED = [
        '29 CFR 1910.178(q)(7)'                    => '29 CFR 1910.178',
        'CA Labor Code §§9150-9161 (SB 132, 2023)' => 'Cal/OSHA CA Labor Code §§9150-9161 (SB 132)',
    ];

    /** @var array<string,int>  regulation_code exacto => id */
    private array $byCode = [];

    /** @var array<int,int>  número de boletín => id */
    private array $bulletinById = [];

    /**
     * @param Collection $standards  filas con ->id, ->regulation_code (y ->regulation_badge)
     */
    public function __construct(Collection $standards)
    {
        foreach ($standards as $s) {
            $code = (string) $s->regulation_code;
            $this->byCode[$code] = (int) $s->id;
            if (preg_match('/^Bulletin\s*#?(\d+)\b/i', $code, $m)) {
                $this->bulletinById[(int) $m[1]] = (int) $s->id;
            }
        }
    }

    /**
     * @return array{id: ?int, kind: string, code: ?string}
     *   kind ∈ exact|csatf|paren|pending
     */
    public function resolve(string $raw, string $bucket = ''): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['id' => null, 'kind' => 'pending', 'code' => null];
        }

        // 0) adjudicado por el panel (por cadena cruda exacta, antes del algoritmo)
        if (isset(self::ADJUDICATED[$raw])) {
            $target = self::ADJUDICATED[$raw];
            if (isset($this->byCode[$target])) {
                return ['id' => $this->byCode[$target], 'kind' => 'adjudicated', 'code' => $target];
            }
        }

        // 1) exacto
        if (isset($this->byCode[$raw])) {
            return ['id' => $this->byCode[$raw], 'kind' => 'exact', 'code' => $raw];
        }

        // 2) csatf → boletín (solo en el bucket csatf). La lookahead (?![0-9A-Za-z])
        //    impide que "#8A" o "#36A" (addenda) se coman como "#8"/"#36".
        if ($bucket === 'csatf'
            && preg_match('/^(?:csatf\s*)?#?\s*(\d+)(?![0-9A-Za-z])/i', $raw, $m)) {
            $n = (int) $m[1];
            if (isset($this->bulletinById[$n])) {
                $code = 'Bulletin #' . $n;
                return ['id' => $this->bulletinById[$n], 'kind' => 'csatf', 'code' => $code];
            }
        }

        // 3) quitar UNA anotación final "(...)" y reintentar EXACTO
        $stripped = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $raw));
        if ($stripped !== $raw && $stripped !== '' && isset($this->byCode[$stripped])) {
            return ['id' => $this->byCode[$stripped], 'kind' => 'paren', 'code' => $stripped];
        }

        return ['id' => null, 'kind' => 'pending', 'code' => null];
    }
}
