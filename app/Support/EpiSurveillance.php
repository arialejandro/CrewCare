<?php

namespace App\Support;

use App\Models\IndicatorTerm;
use App\Models\DailyReport;
use App\Models\cmedic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EpiSurveillance — el AGREGADOR silencioso de la vigilancia epidemiológica (delta #45).
 *
 * ============================ QUÉ HACE Y QUÉ NO ============================
 * LEE las consultas selladas (nunca las escribe), resuelve cada una a grupos indicadores por
 * DOS señales independientes (diagnóstico y medicamento), y devuelve CONTEOS agregados por DÍA
 * DE RODAJE. NO manda correos, NO alerta, NO declara brotes, NO tiene umbrales. Es estadística.
 *
 * ⚠ AGREGADO, NUNCA NOMINAL. La salida SOLO lleva números, etiquetas de día, nombres de LOCACIÓN
 *   y de DEPARTAMENTO, y el conteo de personas. JAMÁS un nombre de persona ni el texto del
 *   diagnóstico: el diagnóstico se usa para RESOLVER el grupo y se descarta; nunca sale de aquí.
 *   El médico llega a los casos por sus propias consultas, como ya lo hace hoy.
 *
 * DOCTRINA: lo clínico cruza por PERSONA y no se filtra por unidad → el agregado concentra TODAS
 * las consultas de la producción (NO se aplica scopeVisibleTo ni el scope por departamento; igual
 * que la bitácora y el conteo). El acceso al PANEL sí está gateado (permission:epi.view).
 */
class EpiSurveillance
{
    /** Ventana del promedio móvil (línea base de la propia producción). */
    const BASELINE_WINDOW = 3;

    /**
     * Construye el dataset del panel.
     *
     * @param  array  $filters  ['location'=>?string, 'department'=>?string] — cortes que RE-ACOTAN
     *                          los conteos (server-side). El corte por grupo es de PANTALLA (se
     *                          muestran todas las series y la vista resalta una).
     * @return array
     */
    public static function build(array $filters = []): array
    {
        $fLocation   = isset($filters['location'])   && $filters['location']   !== '' ? $filters['location']   : null;
        $fDepartment = isset($filters['department']) && $filters['department'] !== '' ? $filters['department'] : null;

        // 1) Línea de tiempo: fechas de rodaje → número de día (derivado, no tecleado).
        $shootDates = ProductionCalendar::shootDates();       // ['Y-m-d', ...] en orden
        $dateToIdx  = [];
        foreach ($shootDates as $i => $d) {
            $dateToIdx[$d] = $i;
        }
        $n = count($shootDates);

        // 2) Contexto del DSR por fecha: locación (corte) y crew_count (denominador).
        [$dsrLoc, $dsrCrew] = self::dsrContext($shootDates);

        // 3) Días (esqueleto del eje X).
        $days = [];
        foreach ($shootDates as $i => $d) {
            $days[] = [
                'shoot_day'  => $i + 1,
                'date'       => $d,
                'label'      => ProductionCalendar::label($i + 1),
                'location'   => $dsrLoc[$d] ?? null,
                'crew_count' => $dsrCrew[$d] ?? null,   // null = sin dato → hueco, nunca cero
            ];
        }

        // 4) Series por grupo + SIN CLASIFICAR (inicializadas en 0 para días de rodaje reales).
        $series = [];
        foreach (array_keys(IndicatorTerm::GROUPS) as $gk) {
            $series[$gk] = array_fill(0, $n, 0);
        }
        $series['_unclassified'] = array_fill(0, $n, 0);
        $total = array_fill(0, $n, 0);

        // 5) Diccionario de términos (una sola lectura).
        $terms = self::activeTerms();

        // 6) Recorre las consultas de la ventana (agregado; sin scope por persona/depto).
        $locationsSet = [];
        $departmentsSet = [];
        $totalConsults = 0;
        $unclassifiedTotal = 0;
        $bySignal = ['dx' => 0, 'med' => 0, 'both' => 0]; // sólo métricas de cobertura, sin nombres

        foreach (self::fetchConsults($shootDates) as $c) {
            $d = $c->eff_date;
            if (! isset($dateToIdx[$d])) {
                continue; // consulta fuera de un día de rodaje real
            }
            $idx = $dateToIdx[$d];

            $loc  = $dsrLoc[$d] ?? null;
            $dept = self::departmentOf($c);
            if ($loc !== null && $loc !== '') { $locationsSet[$loc] = true; }
            $departmentsSet[$dept] = true;

            // Cortes que re-acotan (locación / departamento).
            if ($fLocation !== null && $loc !== $fLocation) { continue; }
            if ($fDepartment !== null && $dept !== $fDepartment) { continue; }

            [$hit, $sig] = self::resolveGroups($c, $terms);

            $totalConsults++;
            $total[$idx]++;
            if (empty($hit)) {
                $series['_unclassified'][$idx]++;
                $unclassifiedTotal++;
            } else {
                foreach ($hit as $gk) {
                    if (isset($series[$gk])) {
                        $series[$gk][$idx]++;
                    }
                }
                if ($sig !== null && isset($bySignal[$sig])) { $bySignal[$sig]++; }
            }
        }

        // 7) Línea base = promedio móvil simple del TOTAL (ya acotado por los cortes).
        $baseline = self::movingAverage($total, self::BASELINE_WINDOW);

        // 8) Denominador (personas ese día): null donde falte → la vista lo pinta como hueco.
        $denominator = array_map(function ($day) { return $day['crew_count']; }, $days);

        ksort($departmentsSet);
        ksort($locationsSet);

        return [
            'days'               => $days,
            'group_labels'       => IndicatorTerm::GROUPS + ['_unclassified' => 'Sin clasificar'],
            'series'             => $series,
            'total'              => $total,
            'baseline'           => $baseline,
            'denominator'        => $denominator,
            'locations'          => array_keys($locationsSet),
            'departments'        => array_keys($departmentsSet),
            'total_consults'     => $totalConsults,
            'unclassified_total' => $unclassifiedTotal,
            'by_signal'          => $bySignal,
            'filters'            => ['location' => $fLocation, 'department' => $fDepartment],
            'has_data'           => $n > 0,
        ];
    }

    // ---------------------------------------------------------------------------------------

    /** Términos activos, en memoria: [ ['group'=>..,'kind'=>..,'term'=>..], ... ]. */
    protected static function activeTerms(): array
    {
        if (! Schema::hasTable('indicator_terms')) {
            return [];
        }
        return IndicatorTerm::query()->where('is_active', 1)
            ->get(['group_key', 'match_kind', 'term'])
            ->map(function ($t) {
                return ['group' => $t->group_key, 'kind' => $t->match_kind, 'term' => $t->term];
            })->all();
    }

    /**
     * Resuelve una consulta a sus grupos por DOS señales independientes:
     *   · diagnóstico (texto libre) contra los términos 'diagnostico'.
     *   · nombre de cada medicamento contra los términos 'medicamento'.
     * Cualquiera sola sirve; se registra si vino por dx, por med, o por ambas (solo para métrica
     * de cobertura, sin identidad). Devuelve [ [group_key...], 'dx'|'med'|'both'|null ].
     */
    protected static function resolveGroups($c, array $terms): array
    {
        $dxNorm = ClinicalTextNormalizer::normalize($c->diagnosis);

        $medNorms = [];
        // El cast del modelo ya puede devolver array; si viniera como texto JSON, se decodifica.
        $items = $c->medication_items;
        if (! is_array($items)) {
            $items = json_decode((string) $items, true);
        }
        if (is_array($items)) {
            foreach ($items as $it) {
                if (is_array($it) && isset($it['name'])) {
                    $medNorms[] = ClinicalTextNormalizer::normalize((string) $it['name']);
                }
            }
        }

        $byDx = [];
        $byMed = [];
        foreach ($terms as $t) {
            if ($t['kind'] === IndicatorTerm::KIND_DIAGNOSIS) {
                if (ClinicalTextNormalizer::containsTerm($dxNorm, $t['term'])) {
                    $byDx[$t['group']] = true;
                }
            } else { // medicamento
                foreach ($medNorms as $mn) {
                    if (ClinicalTextNormalizer::containsTerm($mn, $t['term'])) {
                        $byMed[$t['group']] = true;
                        break;
                    }
                }
            }
        }

        $hit = array_keys($byDx + $byMed);
        if (empty($hit)) {
            return [[], null];
        }
        $signal = (! empty($byDx) && ! empty($byMed)) ? 'both' : (! empty($byDx) ? 'dx' : 'med');
        return [$hit, $signal];
    }

    /** Departamento de la consulta: crew → users.puestodepartamento; lite/vacío → sin asignar. */
    protected static function departmentOf($c): string
    {
        if ($c->id_user === null) {
            return 'Sin departamento asignado';
        }
        $dept = trim((string) ($c->u_dept ?? ''));
        return $dept !== '' ? $dept : 'Sin departamento asignado';
    }

    /**
     * Consultas de la ventana de rodaje. AGREGADO: NO trae nombres de persona — solo el
     * diagnóstico (para resolver el grupo, se descarta), los medicamentos, el departamento del
     * crew y la fecha efectiva. Sin scopeVisibleTo (los agregados concentran a todos).
     */
    protected static function fetchConsults(array $shootDates)
    {
        if (empty($shootDates) || ! Schema::hasTable('cmedic')) {
            return collect();
        }
        $from = $shootDates[0];
        $to   = $shootDates[count($shootDates) - 1];

        return cmedic::query()
            ->leftJoin('users', 'cmedic.id_user', '=', 'users.id')
            ->whereRaw('COALESCE(cmedic.consultation_date, DATE(cmedic.created_at)) BETWEEN ? AND ?', [$from, $to])
            ->get([
                DB::raw('COALESCE(cmedic.consultation_date, DATE(cmedic.created_at)) as eff_date'),
                'cmedic.id_user as id_user',
                'cmedic.diagnosis as diagnosis',
                'cmedic.medication_items as medication_items',
                'users.puestodepartamento as u_dept',
            ]);
    }

    /** Locación (location_name) y denominador (crew_count) por fecha de rodaje, desde el DSR. */
    protected static function dsrContext(array $shootDates): array
    {
        $loc = [];
        $crew = [];
        if (empty($shootDates) || ! Schema::hasTable('daily_reports')) {
            return [$loc, $crew];
        }
        $q = DailyReport::query()->whereIn('report_date', $shootDates);
        $pid = CurrentProduction::id();
        if ($pid && Schema::hasColumn('daily_reports', 'production_id')
            && DailyReport::where('production_id', $pid)->exists()) {
            $q->where('production_id', $pid);
        }
        foreach ($q->orderBy('report_date')->get(['report_date', 'location_name', 'crew_count']) as $r) {
            $d = $r->report_date instanceof \DateTimeInterface
                ? $r->report_date->format('Y-m-d')
                : (string) \Illuminate\Support\Carbon::parse($r->report_date)->toDateString();
            // Si hay más de un DSR por fecha, el último gana (orderBy asc → sobrescribe).
            $loc[$d]  = ($r->location_name !== null && $r->location_name !== '') ? $r->location_name : ($loc[$d] ?? null);
            $crew[$d] = ($r->crew_count !== null && $r->crew_count !== '') ? (int) $r->crew_count : ($crew[$d] ?? null);
        }
        return [$loc, $crew];
    }

    /** Promedio móvil simple (trailing, ventana $w). null en el arranque = hueco honesto. */
    protected static function movingAverage(array $series, int $w): array
    {
        $out = [];
        $n = count($series);
        for ($i = 0; $i < $n; $i++) {
            if ($i < $w - 1) {
                $out[] = null; // aún no hay suficiente historia
                continue;
            }
            $sum = 0;
            for ($j = $i - $w + 1; $j <= $i; $j++) {
                $sum += $series[$j];
            }
            $out[] = round($sum / $w, 2);
        }
        return $out;
    }
}
