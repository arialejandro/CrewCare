<?php

namespace App\Support;

use App\Models\ActionItem;
use App\Models\DailyLog;
use App\Models\DailyReport;
use App\Models\HazardEvent;
use App\Models\InjuryReport;
use App\Models\Production;
use App\Models\ScoutingReport;
use App\Models\SfxEvent;
use App\Models\hazardnotification;
use App\Models\unsafecond;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WrapReportBuilder — el MOTOR del reporte final de wrap (2026-07-24).
 *
 * Lee toda la producción y devuelve las 8 secciones YA CALCULADAS. No pinta nada, no guarda nada,
 * no toca ningún reporte: es de SOLO LECTURA. Lo que devuelve se congela en `wrap_reports.payload`
 * y se sella; a partir de ahí el documento ya no vuelve a pasar por aquí (ver App\Models\WrapReport).
 *
 * ── TRES REGLAS QUE MANDAN SOBRE CUALQUIER OTRA COSA ────────────────────────────────────────
 *
 * 1. CERO NOMBRES DE PERSONAS. Ni uno. El payload es la frontera: si un nombre no entra aquí, no
 *    puede aparecer en el HTML por descuido de una vista. Todo lo que identifica se convierte en
 *    DEPARTAMENTO antes de salir. Esto sostiene la promesa del Acto Inseguro: quien reportó
 *    confiando en que no se le iba a señalar sigue protegido en el documento de cierre.
 *
 * 2. EL HUECO SE DECLARA, NO SE RELLENA. Cuando falta un dato (crew de un día, evento de un
 *    peligro, scouting de una locación) se cuenta y se dice. NUNCA se estima. Un promedio
 *    inventado para que la tabla se vea completa convierte el reporte en ficción, que es lo que
 *    esta app lleva evitando desde el principio.
 *
 * 3. EL REPORTE DECLARA SU PROPIO TAMAÑO DE MUESTRA. Con 8 eventos cruzables no hay conclusión
 *    estadística que sostener. muestra() devuelve el N y la fuerza que ese N permite, y la sección
 *    4 lo imprime junto a sus porcentajes. Un documento que aparenta rigor que no tiene es
 *    exactamente lo que no queremos entregarle a una casa productora.
 *
 * ── CÓMO SE ACOTA UNA PRODUCCIÓN ────────────────────────────────────────────────────────────
 * Sólo `daily_reports` y `scouting_reports` tienen `production_id`. Los demás (accidentes,
 * gemelos, consultas) se acotan por RANGO DE FECHAS contra `productions.start_date/end_date`.
 * Es una decisión, no una carencia disimulada: en una instancia real todo lo que ocurrió entre el
 * primer día de prep y el wrap pertenece a esa producción. Se anota en `avisos`.
 */
class WrapReportBuilder
{
    /**
     * Versión del cálculo. Viaja en el payload congelado para que dentro de dos años se sepa qué
     * lógica produjo esos números. Subirla cuando cambie el significado de algún agregado.
     */
    const VERSION = '1.0';

    /** Debajo de este N el cruce es un indicio suelto, no una tendencia. */
    const MUESTRA_INDICIO = 10;

    /** Debajo de este N hay tendencia legible, pero no conclusión estadística. */
    const MUESTRA_TENDENCIA = 30;

    /** Denominador de las tasas: eventos por cada 100 persona-día. */
    const BASE_TASA = 100;

    /** Nivel de riesgo → puntaje comparable. Cubre las dos notaciones vivas en la base. */
    const NIVELES = [
        'L' => 1, 'BAJO' => 1, 'LOW' => 1,
        'M' => 2, 'MEDIO' => 2, 'MEDIUM' => 2,
        'H' => 3, 'ALTO' => 3, 'HIGH' => 3,
        'E' => 4, 'EXTREMO' => 4, 'EXTREME' => 4, 'VERY HIGH' => 4,
    ];

    /** Puntaje → etiqueta canónica en español. */
    const NIVEL_LABEL = [1 => 'Bajo', 2 => 'Medio', 3 => 'Alto', 4 => 'Extremo'];

    // -----------------------------------------------------------------------------------------
    // ENTRADA
    // -----------------------------------------------------------------------------------------

    /**
     * Calcula el payload completo de las 8 secciones.
     *
     * @param  \App\Models\Production $produccion
     * @param  string|null            $desde  ISO. Por omisión, start_date de la producción.
     * @param  string|null            $hasta  ISO. Por omisión, end_date (o hoy si no hay).
     * @return array
     */
    public static function build($produccion, $desde = null, $hasta = null)
    {
        $ini = $desde ? Carbon::parse($desde)->startOfDay() : self::inicioDe($produccion);
        $fin = $hasta ? Carbon::parse($hasta)->endOfDay()   : self::finDe($produccion);

        $avisos = [];

        // --- Universo acotado. Se calcula UNA vez y lo comparten las 8 secciones. -------------
        $dsrs = self::dsrsDe($produccion, $ini, $fin);
        $scoutings = self::scoutingsDe($produccion, $ini, $fin);
        $logs = self::logsDe($dsrs);
        $actos = self::gemelosDe(hazardnotification::class, $ini, $fin);
        $conds = self::gemelosDe(unsafecond::class, $ini, $fin);
        $lesiones = self::lesionesDe($ini, $fin);
        $consultas = self::consultasDe($ini, $fin);
        $sfx = self::sfxDe($dsrs);

        $s1 = self::seccion1($produccion, $ini, $fin, $dsrs, $scoutings, $avisos);
        $s2 = self::seccion2($scoutings, $avisos);
        $s3 = self::seccion3($dsrs, $logs, $actos, $conds, $lesiones, $consultas, $sfx, $s1, $avisos);
        $s4 = self::seccion4($scoutings, $dsrs, $logs, $actos, $conds, $lesiones, $avisos);
        $s5 = self::seccion5($dsrs, $logs, $actos, $conds, $lesiones);
        $s6 = self::seccion6($dsrs, $actos, $conds, $lesiones, $ini, $fin);
        $s7 = self::seccion7($dsrs, $logs, $actos, $conds, $lesiones, $consultas, $ini);
        $s8 = self::seccion8($s1, $s2, $s3, $s4, $s6);

        return [
            'meta' => [
                'version'      => self::VERSION,
                'generado_en'  => Carbon::now()->format('Y-m-d H:i:s'),
                'periodo'      => ['desde' => $ini->toDateString(), 'hasta' => $fin->toDateString()],
            ],
            's1_alcance'      => $s1,
            's2_anticipado'   => $s2,
            's3_ocurrido'     => $s3,
            's4_contraste'    => $s4,
            's5_cronologia'   => $s5,
            's6_cumplimiento' => $s6,
            's7_tendencias'   => $s7,
            's8_continuidad'  => $s8,
            'avisos'          => array_values(array_unique($avisos)),
        ];
    }

    // -----------------------------------------------------------------------------------------
    // UNIVERSO
    // -----------------------------------------------------------------------------------------

    /**
     * Primer día CUBIERTO por el reporte.
     *
     * ⚠ NO es `productions.start_date`. Esa fecha es el ANCLA DEL DÍA 1 DE RODAJE — así la define
     * ProductionCalendar y así la usa el contador de toda la app. Pero el wrap cubre PREP + RODAJE,
     * y la prep ocurre ANTES: en el corpus los scoutings se levantaron del 25 de junio al 6 de
     * julio y el rodaje arrancó el 7. Anclar el periodo en start_date reportaba "0 días de prep"
     * con cuatro locaciones evaluadas dentro de esos días — el documento habría negado un trabajo
     * que sí se hizo y que la casa productora sí pagó.
     *
     * Por eso el periodo empieza en la PRIMERA ACTIVIDAD REGISTRADA: el scouting más antiguo de la
     * producción o el arranque de rodaje, lo que ocurra primero.
     */
    private static function inicioDe($produccion)
    {
        $candidatas = [];

        if ($produccion && $produccion->start_date) {
            $candidatas[] = Carbon::parse($produccion->start_date)->startOfDay();
        }

        if ($produccion && Schema::hasColumn('scouting_reports', 'production_id')) {
            $q = ScoutingReport::where('production_id', $produccion->id);
            foreach (['date_prep', 'make_date'] as $col) {
                if (! Schema::hasColumn('scouting_reports', $col)) { continue; }
                $f = (clone $q)->whereNotNull($col)->min($col);
                if ($f) { $candidatas[] = Carbon::parse($f)->startOfDay(); }
            }
        }

        $f = DailyReport::when($produccion, function ($q) use ($produccion) {
            if (Schema::hasColumn('daily_reports', 'production_id')) {
                $q->where('production_id', $produccion->id);
            }
        })->min('report_date');
        if ($f) { $candidatas[] = Carbon::parse($f)->startOfDay(); }

        if (! $candidatas) {
            return Carbon::now()->subYear()->startOfDay();
        }

        // ⚠ `date_prep` la teclea una persona y a veces queda en el futuro (el scouting #1 del
        // corpus dice 2026-08-03 para un rodaje de julio). Se descartan las candidatas POSTERIORES
        // al arranque de rodaje: el periodo puede abrirse hacia atrás, nunca hacia adelante.
        $tope = $f ? Carbon::parse($f)->startOfDay() : null;
        if ($tope) {
            $validas = array_filter($candidatas, function ($c) use ($tope) { return $c->lte($tope); });
            if ($validas) { $candidatas = $validas; }
        }

        return min($candidatas);
    }

    /** Último día cubierto: end_date o, si la producción sigue viva, hoy. */
    private static function finDe($produccion)
    {
        if ($produccion && $produccion->end_date) {
            return Carbon::parse($produccion->end_date)->endOfDay();
        }
        return Carbon::now()->endOfDay();
    }

    private static function dsrsDe($produccion, Carbon $ini, Carbon $fin)
    {
        $q = DailyReport::query()->whereBetween('report_date', [$ini->toDateString(), $fin->toDateString()]);
        if ($produccion && Schema::hasColumn('daily_reports', 'production_id')) {
            $q->where('production_id', $produccion->id);
        }
        return $q->orderBy('report_date')->orderBy('id')->get();
    }

    private static function scoutingsDe($produccion, Carbon $ini, Carbon $fin)
    {
        $q = ScoutingReport::query();
        if ($produccion && Schema::hasColumn('scouting_reports', 'production_id')) {
            $q->where('production_id', $produccion->id);
        } else {
            $q->whereBetween('make_date', [$ini->toDateString(), $fin->toDateString()]);
        }
        return $q->orderBy('id')->get();
    }

    /**
     * Hallazgos de bitácora de los DSR del periodo.
     *
     * ⚠ SE EXCLUYEN LOS ESPEJOS DEL DSR MASTER HUB. Reportar un gemelo inyecta automáticamente una
     * copia del hallazgo en la bitácora del día (`sourceable_type` no nulo). Contarlos aquí
     * DUPLICARÍA cada acto y cada condición insegura en los totales del wrap, inflando justo la
     * cifra que la casa productora va a leer primero.
     */
    private static function logsDe($dsrs)
    {
        $ids = $dsrs->pluck('id')->all();
        if (! $ids) {
            return collect();
        }
        $q = DailyLog::whereIn('daily_report_id', $ids);
        if (Schema::hasColumn('daily_logs', 'sourceable_type')) {
            $q->whereNull('sourceable_type');
        }
        return $q->orderBy('daily_report_id')->orderBy('id')->get();
    }

    private static function gemelosDe($clase, Carbon $ini, Carbon $fin)
    {
        return $clase::whereNotNull('date_observed')
            ->whereBetween('date_observed', [$ini->toDateString(), $fin->toDateString()])
            ->orderBy('date_observed')->orderBy('id')->get();
    }

    private static function lesionesDe(Carbon $ini, Carbon $fin)
    {
        return InjuryReport::whereNotNull('incident_date')
            ->whereBetween('incident_date', [$ini->toDateString(), $fin->toDateString()])
            ->orderBy('incident_date')->orderBy('id')->get();
    }

    /**
     * Consultas médicas del periodo — SÓLO EL CONTEO Y LA FECHA.
     *
     * Se usa Query Builder y se seleccionan tres columnas a mano, en vez del modelo completo, para
     * que el diagnóstico, el medicamento, las observaciones y la cédula del médico NO ENTREN
     * SIQUIERA EN MEMORIA en este proceso. El wrap dice cuántas consultas hubo y qué conducta
     * clínica se tomó; nada de lo que se habló en el consultorio sale de ahí.
     */
    private static function consultasDe(Carbon $ini, Carbon $fin)
    {
        if (! Schema::hasTable('cmedic')) {
            return collect();
        }
        $cols = ['id_cmedic', 'consultation_date'];
        if (Schema::hasColumn('cmedic', 'management')) {
            $cols[] = 'management';
        }
        return DB::table('cmedic')->select($cols)
            ->whereNotNull('consultation_date')
            ->whereBetween('consultation_date', [$ini->toDateString(), $fin->toDateString()])
            ->orderBy('consultation_date')->get();
    }

    private static function sfxDe($dsrs)
    {
        if (! Schema::hasTable('sfx_events')) {
            return collect();
        }
        $ids = $dsrs->pluck('id')->all();
        if (! $ids) {
            return collect();
        }
        return SfxEvent::whereIn('daily_report_id', $ids)->orderBy('started_at')->get();
    }

    // -----------------------------------------------------------------------------------------
    // 1 · IDENTIFICACIÓN Y ALCANCE
    // -----------------------------------------------------------------------------------------

    private static function seccion1($produccion, Carbon $ini, Carbon $fin, $dsrs, $scoutings, &$avisos)
    {
        // DÍAS TRABAJADOS, no naturales: los de rodaje son los días DISTINTOS con DSR; los de prep
        // son los hábiles (lunes a sábado) entre el arranque y el primer día de rodaje. Contar
        // días de calendario metería los domingos, que nadie trabajó, y la casa productora
        // leería una producción más larga de la que pagó.
        $fechasRodaje = $dsrs->pluck('report_date')
            ->filter()
            ->map(function ($f) { return Carbon::parse($f)->toDateString(); })
            ->unique()->sort()->values();

        $diasRodaje = $fechasRodaje->count();
        $primerRodaje = $diasRodaje ? Carbon::parse($fechasRodaje->first()) : null;

        $diasPrep = 0;
        if ($primerRodaje && $ini->lt($primerRodaje)) {
            $diasPrep = ProductionCalendar::workingDaysBetween($ini->copy(), $primerRodaje->copy());
        }

        // Crew: se declara la cobertura del dato ANTES de que nadie calcule una tasa con él.
        $conCrew = $dsrs->filter(function ($d) { return $d->crew_count !== null && (int) $d->crew_count > 0; });
        $sinCrew = $dsrs->count() - $conCrew->count();
        $personaDia = (int) $conCrew->sum('crew_count');
        if ($sinCrew > 0) {
            $avisos[] = 'crew_incompleto';
        }

        // Locaciones: las EVALUADAS (con scouting) y las FILMADAS (con DSR) son dos conjuntos
        // distintos, y su diferencia es un hallazgo, no un detalle de implementación.
        $locScouting = $scoutings->pluck('location_name')->filter()->map(function ($n) { return self::normLoc($n); })->unique();
        $locDsr = $dsrs->pluck('location_name')->filter()->map(function ($n) { return self::normLoc($n); })->unique();
        $filmadasSinScouting = $locDsr->diff($locScouting)->count();
        if ($filmadasSinScouting > 0) {
            $avisos[] = 'locaciones_sin_scouting';
        }

        return [
            'produccion'        => $produccion ? (string) $produccion->name : '—',
            'codigo'            => $produccion ? (string) $produccion->code : null,
            'casa_productora'   => ($produccion && $produccion->client_name) ? (string) $produccion->client_name : null,
            'periodo_desde'     => $ini->toDateString(),
            'periodo_hasta'     => $fin->toDateString(),
            'dias_prep'         => $diasPrep,
            'dias_rodaje'       => $diasRodaje,
            'dias_trabajados'   => $diasPrep + $diasRodaje,
            // Carbon 3: diffInDays devuelve float con signo; $fin=endOfDay da N.9999 → (int) trunca al día completo.
            'dias_naturales'    => (int) $ini->diffInDays($fin) + 1,
            // Se imprimen las DOS cifras. Los días trabajados son los que cuentan para las tasas
            // y para lo que se pagó; los naturales son los que ve un calendario. Enseñar sólo una
            // deja al lector sin saber cuál está leyendo, y la diferencia son los domingos.
            'nota_dias'         => 'Se cuentan DÍAS TRABAJADOS, no naturales: ' . $diasPrep . ' de prep (lunes a sábado, '
                . 'el domingo no cuenta) más ' . $diasRodaje . ' días distintos con reporte diario. En el mismo periodo '
                . 'transcurrieron ' . ((int) $ini->diffInDays($fin) + 1) . ' días de calendario.',
            'primer_dia_rodaje' => $primerRodaje ? $primerRodaje->toDateString() : null,
            'ultimo_dia_rodaje' => $diasRodaje ? Carbon::parse($fechasRodaje->last())->toDateString() : null,
            'dsr_emitidos'      => $dsrs->count(),
            'locaciones_evaluadas' => $locScouting->count(),
            'locaciones_filmadas'  => $locDsr->count(),
            'locaciones_filmadas_sin_scouting' => $filmadasSinScouting,
            'crew' => [
                'dias_con_dato'  => $conCrew->count(),
                'dias_sin_dato'  => $sinCrew,
                'maximo'         => $conCrew->count() ? (int) $conCrew->max('crew_count') : null,
                'promedio'       => $conCrew->count() ? (int) round($conCrew->avg('crew_count')) : null,
                'persona_dia'    => $personaDia,
                // El texto que la vista imprime cuando falta el dato. Se redacta AQUÍ para que no
                // haya forma de que una vista lo sustituya por una estimación.
                'nota'           => $sinCrew > 0
                    ? 'De ' . $dsrs->count() . ' días con reporte, ' . $sinCrew . ' no registraron el número de crew. '
                      . 'Las tasas de este documento se calculan sobre los ' . $conCrew->count() . ' días que sí lo registraron. '
                      . 'No se estimó el dato faltante.'
                    : null,
            ],
        ];
    }

    // -----------------------------------------------------------------------------------------
    // 2 · LO QUE SE ANTICIPÓ
    // -----------------------------------------------------------------------------------------

    private static function seccion2($scoutings, &$avisos)
    {
        $locaciones = [];
        $porNivel = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $sinNivel = 0;
        $totalPeligros = 0;
        $conEvento = 0;
        $declaradosSinClasificar = 0;
        $sinMarca = 0;
        $normas = [];
        $sb132 = [];

        foreach ($scoutings as $s) {
            $filas = self::peligrosDe($s);
            $lp = ['nombre' => (string) $s->location_name, 'id' => $s->id, 'peligros' => count($filas),
                   'con_evento' => 0, 'sin_clasificar' => 0, 'sin_marca' => 0, 'por_nivel' => [1=>0,2=>0,3=>0,4=>0]];

            foreach ($filas as $f) {
                $totalPeligros++;
                if ($f['event_id']) {
                    $conEvento++; $lp['con_evento']++;
                } elseif ($f['unclassified']) {
                    $declaradosSinClasificar++; $lp['sin_clasificar']++;
                } else {
                    // Ni evento ni marca: fila anterior a que existiera la marca de "sin clasificar"
                    // (2026-07-24). No es lo mismo que un hueco declarado y no se cuenta como tal.
                    $sinMarca++; $lp['sin_marca']++;
                }
                $n = self::score($f['rating']);
                if ($n) { $porNivel[$n]++; $lp['por_nivel'][$n]++; } else { $sinNivel++; }
                if ($f['badge'] || $f['code']) {
                    $normas[trim($f['badge'] . ' ' . $f['code'])] = true;
                }
            }
            $locaciones[] = $lp;

            // Actividades especiales declaradas (SB-132): el JSON del scouting, sin nombres.
            $d = $s->sb132_details;
            if (is_string($d)) { $d = json_decode($d, true); }
            if (is_array($d)) {
                foreach ($d as $k => $v) {
                    if ($v === false || $v === null || $v === '' || $v === 0 || $v === '0') { continue; }
                    $sb132[is_int($k) ? (string) $v : (string) $k] = true;
                }
            }
        }

        // Normas invocadas también desde el vínculo N:M (standardables), que es la vía normalizada.
        if (Schema::hasTable('standardables') && Schema::hasTable('safety_standards') && $scoutings->count()) {
            $filas = DB::table('standardables')
                ->join('safety_standards', 'safety_standards.id', '=', 'standardables.safety_standard_id')
                ->where('standardables.standardable_type', ScoutingReport::class)
                ->whereIn('standardables.standardable_id', $scoutings->pluck('id')->all())
                ->select('safety_standards.regulation_badge', 'safety_standards.regulation_code')
                ->distinct()->get();
            foreach ($filas as $f) {
                $normas[trim($f->regulation_badge . ' ' . $f->regulation_code)] = true;
            }
        }

        if ($declaradosSinClasificar > 0) {
            $avisos[] = 'peligros_sin_clasificar';
        }

        return [
            'scoutings'     => $scoutings->count(),
            'locaciones'    => $locaciones,
            'peligros'      => $totalPeligros,
            'con_evento'    => $conEvento,
            'sin_clasificar'=> $declaradosSinClasificar,
            'sin_marca'     => $sinMarca,
            'por_nivel'     => $porNivel,
            'sin_nivel'     => $sinNivel,
            'normas'        => array_values(array_filter(array_keys($normas))),
            'sb132'         => array_values(array_filter(array_keys($sb132))),
            // Se enuncia SIEMPRE, tenga o no huecos: que un scouting clasificara todo también es
            // información, y decirlo cada vez evita que el silencio se lea como "no aplica".
            'nota_clasificacion' => $declaradosSinClasificar > 0 || $sinMarca > 0
                ? 'De ' . $totalPeligros . ' peligros identificados, ' . $conEvento . ' quedaron ligados al catálogo de eventos, '
                  . $declaradosSinClasificar . ' se declararon expresamente sin clasificar y ' . $sinMarca
                  . ' provienen de evaluaciones anteriores a que existiera esa marca. Sólo los ligados al catálogo '
                  . 'pueden contrastarse contra lo ocurrido; el resto se reporta como hueco conocido.'
                : 'Los ' . $totalPeligros . ' peligros identificados quedaron ligados al catálogo de eventos, '
                  . 'de modo que todos son contrastables contra lo que ocurrió.',
        ];
    }

    /**
     * Normaliza las filas de `risk_assessment`, que viven en DOS formatos.
     *
     * El viejo usa `label`/`risk` (Bajo/Medio/Alto) y el Amazon MGM usa `hazard`/`rating` (L/M/H/E).
     * Un wrap que sólo entendiera el formato nuevo reportaría CERO peligros anticipados para las
     * locaciones evaluadas antes del cambio — y ese cero se leería como negligencia de la
     * producción, cuando en realidad es una limitación del lector.
     *
     * @return array
     */
    private static function peligrosDe($scouting)
    {
        $ra = $scouting->risk_assessment;
        if (is_string($ra)) { $ra = json_decode($ra, true); }
        if (! is_array($ra)) { return []; }

        $out = [];
        foreach ($ra as $h) {
            if (! is_array($h)) { continue; }
            $out[] = [
                'hazard'       => isset($h['hazard']) ? (string) $h['hazard'] : (isset($h['label']) ? (string) $h['label'] : ''),
                'rating'       => isset($h['rating']) ? $h['rating'] : (isset($h['risk']) ? $h['risk'] : null),
                'residual'     => isset($h['residual']) ? $h['residual'] : null,
                'control'      => isset($h['control']) ? (string) $h['control'] : (isset($h['note']) ? (string) $h['note'] : ''),
                'event_id'     => ! empty($h['event_id']) ? (int) $h['event_id'] : null,
                'event_name'   => isset($h['event_name']) ? (string) $h['event_name'] : null,
                'unclassified' => ! empty($h['unclassified']),
                'badge'        => isset($h['badge']) ? (string) $h['badge'] : null,
                'code'         => isset($h['code']) ? (string) $h['code'] : null,
            ];
        }
        return $out;
    }

    // -----------------------------------------------------------------------------------------
    // 3 · LO QUE REALMENTE PASÓ
    // -----------------------------------------------------------------------------------------

    private static function seccion3($dsrs, $logs, $actos, $conds, $lesiones, $consultas, $sfx, $s1, &$avisos)
    {
        $registrables = $lesiones->filter(function ($i) { return (int) $i->is_recordable === 1; });
        $diasAusencia = (int) $lesiones->sum('days_away_from_work');
        $diasRestringido = (int) $lesiones->sum('days_restricted_work');

        // Departamentos afectados — NUNCA la persona. Es el nivel de detalle al que la casa
        // productora puede actuar (reforzar a un departamento) sin señalar a nadie.
        $deptos = [];
        foreach ($lesiones as $i) {
            $d = trim((string) $i->department);
            if ($d !== '') { $deptos[$d] = (isset($deptos[$d]) ? $deptos[$d] : 0) + 1; }
        }
        foreach ($conds as $c) {
            $d = trim((string) $c->involved_department);
            if ($d !== '') { $deptos[$d] = (isset($deptos[$d]) ? $deptos[$d] : 0) + 1; }
        }
        foreach ($actos as $a) {
            if (empty($a->involved_user_id)) { continue; }
            $d = trim(InvolvedResolver::departmentNames($a->involved_user_id));
            if ($d !== '') { $deptos[$d] = (isset($deptos[$d]) ? $deptos[$d] : 0) + 1; }
        }
        arsort($deptos);

        // Conducta clínica: es el único eje del módulo médico que es CATÁLOGO CERRADO. El
        // diagnóstico es texto libre y además es información clínica — no entra al wrap.
        $manejo = [];
        foreach ($consultas as $c) {
            $m = isset($c->management) ? $c->management : null;
            $claves = is_string($m) ? json_decode($m, true) : $m;
            if (! is_array($claves)) { $claves = $m ? [$m] : []; }
            foreach ($claves as $k) {
                $lbl = isset(\App\Models\cmedic::MANAGEMENT_OPTIONS[$k]) ? \App\Models\cmedic::MANAGEMENT_OPTIONS[$k] : (string) $k;
                if ($lbl === '') { continue; }
                $manejo[$lbl] = (isset($manejo[$lbl]) ? $manejo[$lbl] : 0) + 1;
            }
        }
        arsort($manejo);

        $totalEventos = $logs->count() + $actos->count() + $conds->count() + $lesiones->count();
        $personaDia = (int) $s1['crew']['persona_dia'];

        return [
            'hallazgos_dsr'   => $logs->count(),
            'actos'           => $actos->count(),
            'condiciones'     => $conds->count(),
            'lesiones'        => $lesiones->count(),
            'registrables'    => $registrables->count(),
            'dias_ausencia'   => $diasAusencia,
            'dias_restringido'=> $diasRestringido,
            'consultas'       => $consultas->count(),
            'manejo_clinico'  => $manejo,
            'sfx'             => $sfx->count(),
            'sfx_detalle'     => $sfx->map(function ($e) {
                return ['efecto' => (string) $e->effect_label, 'estado' => (string) $e->status,
                        'inicio' => $e->started_at ? Carbon::parse($e->started_at)->toDateString() : null];
            })->values()->all(),
            'departamentos'   => $deptos,
            'total_eventos'   => $totalEventos,
            'persona_dia'     => $personaDia,
            'tasas'           => self::tasas($personaDia, [
                'eventos'      => $totalEventos,
                'registrables' => $registrables->count(),
                'consultas'    => $consultas->count(),
            ]),
            // El límite se declara EN EL DOCUMENTO. La TRIR de OSHA se calcula sobre horas
            // trabajadas y CrewCare no las captura; multiplicar los días por una jornada supuesta
            // produciría una cifra con apariencia oficial y sin respaldo. Se dice qué falta.
            'nota_tasa' => $personaDia > 0
                ? 'Las tasas se expresan por cada ' . self::BASE_TASA . ' persona-día (suma del crew de cada día con dato: '
                  . number_format($personaDia) . '). No se reporta TRIR de OSHA porque su denominador son HORAS '
                  . 'trabajadas y este sistema no las registra; estimarlas a partir de una jornada supuesta daría una '
                  . 'cifra de apariencia oficial sin respaldo.'
                : 'No se pueden calcular tasas: ningún día del periodo registró el número de crew. Se reportan conteos absolutos.',
        ];
    }

    /** Tasas por 100 persona-día. Devuelve null cuando no hay denominador: no se inventa uno. */
    private static function tasas($personaDia, array $conteos)
    {
        $out = [];
        foreach ($conteos as $k => $n) {
            $out[$k] = $personaDia > 0 ? round(($n / $personaDia) * self::BASE_TASA, 2) : null;
        }
        return $out;
    }

    // -----------------------------------------------------------------------------------------
    // 4 · PREDICHO vs. REAL — la sección que nadie más puede escribir
    // -----------------------------------------------------------------------------------------

    private static function seccion4($scoutings, $dsrs, $logs, $actos, $conds, $lesiones, &$avisos)
    {
        // Índice locación normalizada → scouting. Se usa igualdad EXACTA tras normalizar (minúsculas,
        // sin acentos, espacios colapsados). NO se hace parecido difuso a propósito: un "Atlampa /
        // Pino" emparejado con "Bodega Atlampa" por parecerse crearía un cruce falso, y un cruce
        // falso es peor que un hueco declarado — el hueco se ve, la mentira no.
        $porLoc = [];
        foreach ($scoutings as $s) {
            $k = self::normLoc($s->location_name);
            if ($k !== '') { $porLoc[$k] = $s->id; }
        }
        $dsrLoc = [];
        foreach ($dsrs as $d) {
            $dsrLoc[$d->id] = self::normLoc($d->location_name);
        }

        // --- Ocurrencias con evento del catálogo, atadas a una locación evaluada ---------------
        $ocurrencias = [];   // cada una: scouting_id|null, event_id, nivel_real, tipo, fecha

        foreach ($actos->concat($conds) as $g) {
            if (empty($g->hazard_event_id)) { continue; }
            $sid = ! empty($g->scouting_report_id) ? (int) $g->scouting_report_id : null;
            if ($sid === null) {
                $k = self::normLoc($g->name_loc);
                $sid = isset($porLoc[$k]) ? $porLoc[$k] : null;
            }
            $ocurrencias[] = [
                'scouting_id' => $sid,
                'event_id'    => (int) $g->hazard_event_id,
                'nivel'       => self::score($g->override_risk_level ?: $g->risk_level),
                'tipo'        => $g instanceof hazardnotification ? 'acto' : 'condicion',
                'fecha'       => $g->date_observed ? Carbon::parse($g->date_observed)->toDateString() : null,
            ];
        }

        foreach ($lesiones as $i) {
            if (empty($i->hazard_event_id)) { continue; }
            $k = self::normLoc($i->location ?: $i->incident_location);
            $ocurrencias[] = [
                'scouting_id' => isset($porLoc[$k]) ? $porLoc[$k] : null,
                'event_id'    => (int) $i->hazard_event_id,
                'nivel'       => self::score($i->override_risk_level ?: $i->risk_level),
                'tipo'        => 'lesion',
                'fecha'       => $i->incident_date ? Carbon::parse($i->incident_date)->toDateString() : null,
            ];
        }

        foreach ($logs as $l) {
            if (empty($l->hazard_event_id)) { continue; }
            $k = isset($dsrLoc[$l->daily_report_id]) ? $dsrLoc[$l->daily_report_id] : '';
            $ocurrencias[] = [
                'scouting_id' => isset($porLoc[$k]) ? $porLoc[$k] : null,
                'event_id'    => (int) $l->hazard_event_id,
                // Un hallazgo de bitácora no lleva nivel propio: hereda el del catálogo, que es
                // la severidad por omisión del tipo de evento. Se marca como heredado.
                'nivel'       => null,
                'tipo'        => 'hallazgo',
                'fecha'       => null,
            ];
        }

        // --- (a) POR LOCACIÓN -----------------------------------------------------------------
        $nombres = self::nombresDeEventos(array_map(function ($o) { return $o['event_id']; }, $ocurrencias));
        $porLocacion = [];
        $totalAnticipadoMaterializado = 0;
        $totalNoAnticipado = 0;
        $totalAnticipadoNoOcurrio = 0;

        foreach ($scoutings as $s) {
            $predichos = [];
            foreach (self::peligrosDe($s) as $f) {
                if ($f['event_id']) {
                    $predichos[$f['event_id']] = ['rating' => $f['rating'], 'hazard' => $f['hazard']];
                }
            }
            $ocurridos = [];
            foreach ($ocurrencias as $o) {
                if ($o['scouting_id'] === (int) $s->id) {
                    $ocurridos[$o['event_id']] = true;
                }
            }

            $aciertos = array_intersect_key($predichos, $ocurridos);
            $sorpresas = array_diff_key($ocurridos, $predichos);
            $noMaterializados = array_diff_key($predichos, $ocurridos);

            $totalAnticipadoMaterializado += count($aciertos);
            $totalNoAnticipado += count($sorpresas);
            $totalAnticipadoNoOcurrio += count($noMaterializados);

            $porLocacion[] = [
                'locacion'   => (string) $s->location_name,
                'predichos'  => count($predichos),
                'anticipados_que_ocurrieron' => array_values(array_map(function ($id) use ($predichos, $nombres) {
                    return ['evento' => isset($nombres[$id]) ? $nombres[$id] : ('Evento #' . $id),
                            'predicho' => self::label(self::score($predichos[$id]['rating']))];
                }, array_keys($aciertos))),
                'no_anticipados' => array_values(array_map(function ($id) use ($nombres) {
                    return ['evento' => isset($nombres[$id]) ? $nombres[$id] : ('Evento #' . $id)];
                }, array_keys($sorpresas))),
                'anticipados_sin_ocurrir' => count($noMaterializados),
            ];
        }

        $sinLocalizar = 0;
        foreach ($ocurrencias as $o) {
            if ($o['scouting_id'] === null) { $sinLocalizar++; }
        }
        if ($sinLocalizar > 0) { $avisos[] = 'eventos_sin_locacion'; }

        // --- (b) CALIBRACIÓN DE SEVERIDAD -----------------------------------------------------
        // Se comparan SÓLO los eventos que tenían predicción Y ocurrieron CON nivel real propio.
        // Los hallazgos de bitácora quedan fuera porque no traen nivel: meterlos con el nivel por
        // omisión del catálogo mediría el catálogo, no la evaluación de esta producción.
        $pares = [];
        foreach ($scoutings as $s) {
            $predichos = [];
            foreach (self::peligrosDe($s) as $f) {
                if ($f['event_id']) { $predichos[$f['event_id']] = self::score($f['rating']); }
            }
            foreach ($ocurrencias as $o) {
                if ($o['scouting_id'] !== (int) $s->id || $o['nivel'] === null) { continue; }
                if (! isset($predichos[$o['event_id']]) || $predichos[$o['event_id']] === null) { continue; }
                $pares[] = [
                    'evento'   => isset($nombres[$o['event_id']]) ? $nombres[$o['event_id']] : ('Evento #' . $o['event_id']),
                    'locacion' => (string) $s->location_name,
                    'predicho' => $predichos[$o['event_id']],
                    'real'     => $o['nivel'],
                    'delta'    => $o['nivel'] - $predichos[$o['event_id']],
                ];
            }
        }
        $subestimados = 0; $acertados = 0; $sobreestimados = 0;
        foreach ($pares as $p) {
            if ($p['delta'] > 0) { $subestimados++; } elseif ($p['delta'] < 0) { $sobreestimados++; } else { $acertados++; }
        }

        // Materialización por nivel predicho: la pregunta del owner — ¿lo que se calificó Alto
        // produjo algo, y lo que produjo algo estaba calificado bajo?
        $materializacion = [1 => ['predichos'=>0,'ocurrieron'=>0], 2 => ['predichos'=>0,'ocurrieron'=>0],
                            3 => ['predichos'=>0,'ocurrieron'=>0], 4 => ['predichos'=>0,'ocurrieron'=>0]];
        foreach ($scoutings as $s) {
            $ocurridos = [];
            foreach ($ocurrencias as $o) {
                if ($o['scouting_id'] === (int) $s->id) { $ocurridos[$o['event_id']] = true; }
            }
            foreach (self::peligrosDe($s) as $f) {
                if (! $f['event_id']) { continue; }
                $n = self::score($f['rating']);
                if (! $n) { continue; }
                $materializacion[$n]['predichos']++;
                if (isset($ocurridos[$f['event_id']])) { $materializacion[$n]['ocurrieron']++; }
            }
        }
        foreach ($materializacion as $n => $m) {
            $materializacion[$n]['tasa'] = $m['predichos'] > 0
                ? round(($m['ocurrieron'] / $m['predichos']) * 100, 1) : null;
        }

        $nCruce = count($ocurrencias);
        $muestra = self::muestra($nCruce, count($pares));

        return [
            'por_locacion'        => $porLocacion,
            'eventos_cruzados'    => $nCruce,
            'eventos_sin_locacion'=> $sinLocalizar,
            'anticipados_que_ocurrieron' => $totalAnticipadoMaterializado,
            'no_anticipados'      => $totalNoAnticipado,
            'anticipados_sin_ocurrir' => $totalAnticipadoNoOcurrio,
            'calibracion' => [
                'pares'          => $pares,
                'subestimados'   => $subestimados,
                'acertados'      => $acertados,
                'sobreestimados' => $sobreestimados,
                'materializacion'=> $materializacion,
            ],
            'muestra' => $muestra,
            // Lo que NO significa un peligro anticipado que no ocurrió. Sin esta línea, la tabla
            // se lee como "sobraron N evaluaciones", cuando la lectura más probable es la
            // contraria: se anticipó y el control funcionó. El documento no puede dejar que el
            // lector saque solo la conclusión equivocada.
            'nota_no_materializados' => 'Un peligro anticipado que no se materializó NO es una evaluación sobrante: '
                . 'lo más probable es que el control declarado en prep haya funcionado. Este documento no puede '
                . 'distinguir "no ocurrió porque se previno" de "no ocurrió porque nunca iba a ocurrir", y no lo afirma.',
        ];
    }

    /**
     * Fuerza de la muestra. El wrap tiene que DECIR cuántos datos sostienen sus porcentajes.
     *
     * Con 8 eventos cruzables, un "50 % de subestimación" son cuatro casos. Presentarlo como
     * conclusión estadística sería aparentar un rigor que el dato no tiene — y eso es justo lo que
     * esta app evita: la casa productora tomaría decisiones de presupuesto sobre ruido.
     *
     * @return array
     */
    private static function muestra($nEventos, $nPares)
    {
        if ($nEventos < self::MUESTRA_INDICIO) {
            $fuerza = 'indicio';
            $texto = $nEventos . ' eventos cruzables y ' . $nPares . ' comparaciones de severidad: muestra muy pequeña. '
                   . 'Lo que sigue son INDICIOS de casos concretos, no una medición. Un solo evento mueve los porcentajes '
                   . 'varias decenas de puntos. Léase como material para revisar caso por caso, nunca como veredicto '
                   . 'sobre la metodología.';
        } elseif ($nEventos < self::MUESTRA_TENDENCIA) {
            $fuerza = 'tendencia';
            $texto = $nEventos . ' eventos cruzables y ' . $nPares . ' comparaciones de severidad: muestra limitada. '
                   . 'Léase como TENDENCIA, no como conclusión estadística. Sirve para orientar el scouting del '
                   . 'siguiente proyecto; no basta para declarar mal calibrada la metodología.';
        } else {
            $fuerza = 'suficiente';
            $texto = $nEventos . ' eventos cruzables y ' . $nPares . ' comparaciones de severidad. La muestra sostiene '
                   . 'la lectura de los porcentajes, aunque sigue siendo de una sola producción: la comparación entre '
                   . 'proyectos es la que da una base sólida.';
        }
        return ['n_eventos' => $nEventos, 'n_pares' => $nPares, 'fuerza' => $fuerza, 'texto' => $texto];
    }

    // -----------------------------------------------------------------------------------------
    // 5 · DESGLOSE CRONOLÓGICO
    // -----------------------------------------------------------------------------------------

    private static function seccion5($dsrs, $logs, $actos, $conds, $lesiones)
    {
        $fechaDsr = [];
        $locDsr = [];
        foreach ($dsrs as $d) {
            $fechaDsr[$d->id] = $d->report_date ? Carbon::parse($d->report_date)->toDateString() : null;
            $locDsr[$d->id] = (string) $d->location_name;
        }
        $nombres = self::nombresDeEventos(
            $logs->pluck('hazard_event_id')->concat($actos->pluck('hazard_event_id'))
                 ->concat($conds->pluck('hazard_event_id'))->concat($lesiones->pluck('hazard_event_id'))->all()
        );

        $filas = [];

        foreach ($logs as $l) {
            $filas[] = self::entrada(
                isset($fechaDsr[$l->daily_report_id]) ? $fechaDsr[$l->daily_report_id] : null,
                'Hallazgo de bitácora',
                isset($locDsr[$l->daily_report_id]) ? $locDsr[$l->daily_report_id] : '',
                null,
                (string) $l->description,
                (string) $l->action_taken,
                $l->hazard_event_id && isset($nombres[$l->hazard_event_id]) ? $nombres[$l->hazard_event_id] : null,
                null,
                trim($l->regulation_badge . ' ' . $l->regulation_code),
                null
            );
        }

        foreach ($actos as $a) {
            // DEPARTAMENTO, jamás la persona. involved_user_id existe precisamente para poder
            // avisarle a su jefe sin escribir el nombre en ningún documento.
            $dep = ! empty($a->involved_user_id) ? InvolvedResolver::departmentNames($a->involved_user_id) : '';
            $filas[] = self::entrada(
                $a->date_observed ? Carbon::parse($a->date_observed)->toDateString() : null,
                'Acto inseguro',
                (string) ($a->name_loc ?: $a->location_hazard_unsafe_act),
                $dep,
                (string) $a->description_hazard_unsafe_act,
                trim((string) $a->action_taken . ' ' . (string) $a->suggestions_corrective_action),
                $a->hazard_event_id && isset($nombres[$a->hazard_event_id]) ? $nombres[$a->hazard_event_id] : null,
                self::label(self::score($a->override_risk_level ?: $a->risk_level)),
                trim($a->regulation_badge . ' ' . $a->regulation_code),
                self::estado($a->action_status)
            );
        }

        foreach ($conds as $c) {
            $filas[] = self::entrada(
                $c->date_observed ? Carbon::parse($c->date_observed)->toDateString() : null,
                'Condición insegura',
                (string) ($c->name_loc ?: $c->location_unsafe_cond),
                (string) $c->involved_department,
                (string) $c->description_unsafe_cond,
                trim((string) $c->action_taken . ' ' . (string) $c->corrective_action),
                $c->hazard_event_id && isset($nombres[$c->hazard_event_id]) ? $nombres[$c->hazard_event_id] : null,
                self::label(self::score($c->override_risk_level ?: $c->risk_level)),
                trim($c->regulation_badge . ' ' . $c->regulation_code),
                self::estado($c->action_status)
            );
        }

        foreach ($lesiones as $i) {
            $rep = (int) $i->is_recordable === 1
                ? 'Registrable OSHA · ' . (int) $i->days_away_from_work . ' días de ausencia y '
                  . (int) $i->days_restricted_work . ' de trabajo restringido.'
                : 'No registrable (primeros auxilios).';
            $filas[] = self::entrada(
                $i->incident_date ? Carbon::parse($i->incident_date)->toDateString() : null,
                'Accidente',
                (string) ($i->location ?: $i->incident_location),
                (string) $i->department,
                (string) $i->what_happened,
                trim((string) $i->preventions),
                $i->hazard_event_id && isset($nombres[$i->hazard_event_id]) ? $nombres[$i->hazard_event_id] : null,
                self::label(self::score($i->override_risk_level ?: $i->risk_level)),
                trim($i->regulation_badge . ' ' . $i->regulation_code),
                null,
                trim((string) $i->what_caused),
                $rep
            );
        }

        usort($filas, function ($a, $b) {
            if ($a['fecha'] === $b['fecha']) { return strcmp($a['tipo'], $b['tipo']); }
            if ($a['fecha'] === null) { return 1; }
            if ($b['fecha'] === null) { return -1; }
            return strcmp($a['fecha'], $b['fecha']);
        });

        // Etiqueta de día trabajado, ya resuelta: la vista no vuelve a consultar el calendario.
        foreach ($filas as $k => $f) {
            $filas[$k]['dia'] = $f['fecha'] ? ProductionCalendar::labelFor($f['fecha']) : '—';
        }

        return ['entradas' => $filas, 'total' => count($filas)];
    }

    /** Una entrada del desglose. El orden de los campos ES la narrativa: qué · por qué · qué sigue. */
    private static function entrada($fecha, $tipo, $locacion, $departamento, $hallazgo, $decision,
                                    $evento, $nivel, $norma, $estado, $causa = null, $repercusion = null)
    {
        return [
            'fecha'        => $fecha,
            'tipo'         => $tipo,
            'locacion'     => self::limpia($locacion),
            'departamento' => self::limpia($departamento),
            'hallazgo'     => self::limpia($hallazgo),
            'decision'     => self::limpia($decision),
            'causa'        => self::limpia($causa),
            'evento'       => $evento,
            'nivel'        => $nivel,
            'norma'        => self::limpia($norma),
            'estado'       => $estado,
            'repercusion'  => self::limpia($repercusion),
        ];
    }

    // -----------------------------------------------------------------------------------------
    // 6 · CUMPLIMIENTO
    // -----------------------------------------------------------------------------------------

    private static function seccion6($dsrs, $actos, $conds, $lesiones, Carbon $ini, Carbon $fin)
    {
        // LOS TRES ESTADOS DE LA JUNTA, y el tercero es el importante: "sin declarar" no es lo
        // mismo que "no se hizo". Fundirlos ocultaría un hueco de registro o acusaría a la
        // producción de algo que quizá sí hizo. Se reportan separados.
        $realizadas = 0; $noRealizadas = 0; $sinDeclarar = 0;
        $temas = [];
        foreach ($dsrs as $d) {
            $h = $d->safety_meeting_held;
            if ($h === null || $h === '') { $sinDeclarar++; }
            elseif ((int) $h === 1) {
                $realizadas++;
                $t = trim((string) $d->safety_meeting_topics);
                if ($t !== '') { $temas[$t] = (isset($temas[$t]) ? $temas[$t] : 0) + 1; }
            } else { $noRealizadas++; }
        }
        arsort($temas);

        // Acciones correctivas: el universo son las atadas a documentos DE ESTE PERIODO.
        $acciones = collect();
        if (Schema::hasTable('action_items')) {
            $mapa = [
                InjuryReport::class      => $lesiones->pluck('id')->all(),
                hazardnotification::class=> $actos->pluck('id')->all(),
                unsafecond::class        => $conds->pluck('id')->all(),
                DailyLog::class          => DailyLog::whereIn('daily_report_id', $dsrs->pluck('id')->all())->pluck('id')->all(),
            ];
            $q = ActionItem::query()->where(function ($w) use ($mapa) {
                foreach ($mapa as $tipo => $ids) {
                    if (! $ids) { continue; }
                    $w->orWhere(function ($x) use ($tipo, $ids) {
                        $x->where('actionable_type', $tipo)->whereIn('actionable_id', $ids);
                    });
                }
            });
            $acciones = $q->get();
        }

        $abiertas = 0; $cerradas = 0; $vencidas = 0; $diasCierre = [];
        foreach ($acciones as $a) {
            $cerrada = $a->status === 'closed' || $a->closed_at !== null;
            if ($cerrada) {
                $cerradas++;
                if ($a->closed_at && $a->created_at) {
                    $diasCierre[] = Carbon::parse($a->created_at)->diffInDays(Carbon::parse($a->closed_at));
                }
            } else {
                $abiertas++;
                // Vencida = con fecha compromiso ya pasada al cierre del periodo. Se mide contra el
                // fin del rango, no contra hoy: el reporte describe la producción, no el momento
                // en que se imprime — si no, el mismo documento cambiaría de cifra cada semana.
                if ($a->due_date && Carbon::parse($a->due_date)->lt($fin)) { $vencidas++; }
            }
        }

        // Notificaciones a autoridad: sin nombre de quien notificó.
        $notificaciones = [];
        foreach ($lesiones as $i) {
            if ((int) $i->notified_to_worksafe === 1) {
                $notificaciones[] = [
                    'fecha' => $i->date_notified ? Carbon::parse($i->date_notified)->toDateString() : null,
                    'nota'  => self::limpia((string) $i->notified_comment),
                ];
            }
            $extra = $i->authority_notifications;
            if (is_string($extra)) { $extra = json_decode($extra, true); }
            if (is_array($extra)) {
                foreach ($extra as $n) {
                    if (! is_array($n)) { continue; }
                    $notificaciones[] = [
                        'fecha' => isset($n['date']) ? (string) $n['date'] : null,
                        'nota'  => self::limpia(isset($n['authority']) ? (string) $n['authority'] : ''),
                    ];
                }
            }
        }

        return [
            'juntas' => [
                'realizadas'   => $realizadas,
                'no_realizadas'=> $noRealizadas,
                'sin_declarar' => $sinDeclarar,
                'dias'         => $dsrs->count(),
                'temas'        => $temas,
            ],
            'acciones' => [
                'total'          => $acciones->count(),
                'abiertas'       => $abiertas,
                'cerradas'       => $cerradas,
                'vencidas'       => $vencidas,
                'dias_cierre_promedio' => count($diasCierre) ? round(array_sum($diasCierre) / count($diasCierre), 1) : null,
                // Carbon 3: diffInDays es float (created_at→closed_at con hora) → redondear a día entero.
                'dias_cierre_max'      => count($diasCierre) ? (int) round(max($diasCierre)) : null,
            ],
            'notificaciones_autoridad' => $notificaciones,
            'lesiones_pendientes_notificar' => $lesiones->filter(function ($i) {
                return (int) $i->is_recordable === 1 && (int) $i->notified_to_worksafe !== 1;
            })->count(),
        ];
    }

    // -----------------------------------------------------------------------------------------
    // 7 · TENDENCIAS (series listas para dibujar; las gráficas son SVG puro en la vista)
    // -----------------------------------------------------------------------------------------

    private static function seccion7($dsrs, $logs, $actos, $conds, $lesiones, $consultas, Carbon $ini)
    {
        // Eje X: DÍAS TRABAJADOS con reporte, en orden. No días naturales — un hueco de domingo
        // en la gráfica insinuaría un día sin vigilancia que en realidad nadie trabajó.
        $ejes = [];
        foreach ($dsrs as $d) {
            if (! $d->report_date) { continue; }
            $f = Carbon::parse($d->report_date)->toDateString();
            if (! isset($ejes[$f])) {
                $ejes[$f] = ['fecha' => $f, 'dia' => ProductionCalendar::labelFor($f),
                             'hallazgos' => 0, 'gemelos' => 0, 'lesiones' => 0, 'consultas' => 0, 'crew' => null];
            }
            if ($d->crew_count !== null && (int) $d->crew_count > 0) {
                $ejes[$f]['crew'] = max((int) $ejes[$f]['crew'], (int) $d->crew_count);
            }
        }
        $fechaDsr = [];
        foreach ($dsrs as $d) { $fechaDsr[$d->id] = $d->report_date ? Carbon::parse($d->report_date)->toDateString() : null; }

        foreach ($logs as $l) {
            $f = isset($fechaDsr[$l->daily_report_id]) ? $fechaDsr[$l->daily_report_id] : null;
            if ($f && isset($ejes[$f])) { $ejes[$f]['hallazgos']++; }
        }
        foreach ($actos->concat($conds) as $g) {
            $f = $g->date_observed ? Carbon::parse($g->date_observed)->toDateString() : null;
            if ($f && isset($ejes[$f])) { $ejes[$f]['gemelos']++; }
        }
        foreach ($lesiones as $i) {
            $f = $i->incident_date ? Carbon::parse($i->incident_date)->toDateString() : null;
            if ($f && isset($ejes[$f])) { $ejes[$f]['lesiones']++; }
        }
        foreach ($consultas as $c) {
            $f = $c->consultation_date ? Carbon::parse($c->consultation_date)->toDateString() : null;
            if ($f && isset($ejes[$f])) { $ejes[$f]['consultas']++; }
        }
        ksort($ejes);

        // Riesgo real observado, por nivel.
        $niveles = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($actos->concat($conds) as $g) {
            $n = self::score($g->override_risk_level ?: $g->risk_level);
            if ($n) { $niveles[$n]++; }
        }
        foreach ($lesiones as $i) {
            $n = self::score($i->override_risk_level ?: $i->risk_level);
            if ($n) { $niveles[$n]++; }
        }

        // Apertura vs. cierre acumulados, por día trabajado.
        $curva = [];
        $abiertasAcum = 0; $cerradasAcum = 0;
        if (Schema::hasTable('action_items')) {
            $porFecha = [];
            $ids = ['abre' => [], 'cierra' => []];
            foreach (ActionItem::all() as $a) {
                if ($a->created_at) { $ids['abre'][] = Carbon::parse($a->created_at)->toDateString(); }
                if ($a->closed_at)  { $ids['cierra'][] = Carbon::parse($a->closed_at)->toDateString(); }
            }
            foreach ($ejes as $f => $e) {
                $abiertasAcum += count(array_keys($ids['abre'], $f));
                $cerradasAcum += count(array_keys($ids['cierra'], $f));
                $curva[] = ['dia' => $e['dia'], 'abiertas' => $abiertasAcum, 'cerradas' => $cerradasAcum];
            }
        }

        return [
            'serie_dias'  => array_values($ejes),
            'por_nivel'   => $niveles,
            'curva_acciones' => $curva,
        ];
    }

    // -----------------------------------------------------------------------------------------
    // 8 · CONTINUIDAD — recomendaciones DERIVADAS, cada una anclada a un número de este reporte
    // -----------------------------------------------------------------------------------------

    /**
     * Cada recomendación nace de una condición que se cumplió en ESTA producción y cita el dato
     * que la disparó. Nada de buenas intenciones genéricas: si la condición no se dio, la
     * recomendación no aparece. Eso es lo que separa este bloque de un texto auto-redactado.
     */
    private static function seccion8($s1, $s2, $s3, $s4, $s6)
    {
        $r = [];

        if ($s2['sin_clasificar'] > 0 || $s2['sin_marca'] > 0) {
            $n = $s2['sin_clasificar'] + $s2['sin_marca'];
            $r[] = self::reco('corto', 'Cerrar el hueco del catálogo de eventos',
                $n . ' de ' . $s2['peligros'] . ' peligros identificados no quedaron ligados al catálogo.',
                'Revisar esos peligros con el equipo de seguridad y darlos de alta como eventos nuevos. Cada uno que se '
                . 'quede fuera es un peligro que el wrap del siguiente proyecto tampoco va a poder contrastar.');
        }

        if ($s1['locaciones_filmadas_sin_scouting'] > 0) {
            $r[] = self::reco('corto', 'Evaluar toda locación antes de filmarla',
                $s1['locaciones_filmadas_sin_scouting'] . ' locaciones aparecen en los reportes diarios sin un scouting '
                . 'que las evalúe (' . $s1['locaciones_evaluadas'] . ' evaluadas contra ' . $s1['locaciones_filmadas'] . ' filmadas).',
                'Condicionar el llamado a que exista scouting de la locación. Sin evaluación previa no hay predicción '
                . 'que contrastar, y esos días quedan fuera del análisis de este documento.');
        }

        if ($s1['crew']['dias_sin_dato'] > 0) {
            $r[] = self::reco('corto', 'Capturar el número de crew todos los días',
                $s1['crew']['dias_sin_dato'] . ' de ' . $s1['dsr_emitidos'] . ' días no registraron crew.',
                'Sin ese número no hay tasas relativas: los conteos absolutos de una producción de 150 personas y una de '
                . '40 no se pueden comparar. Es un campo, y habilita toda la sección de tasas.');
        }

        if ($s4['no_anticipados'] > 0) {
            $r[] = self::reco('medio', 'Incorporar al scouting los tipos de evento que sorprendieron',
                $s4['no_anticipados'] . ' eventos ocurrieron en locaciones cuyo scouting no los había identificado.',
                'Añadir esos tipos a la lista de verificación del scouting para locaciones equivalentes. Es la forma '
                . 'más directa que tiene este reporte de mejorar el siguiente.');
        }

        $cal = $s4['calibracion'];
        if ($cal['subestimados'] > 0) {
            $r[] = self::reco('medio', 'Revisar la calibración de severidad',
                $cal['subestimados'] . ' de ' . count($cal['pares']) . ' comparaciones resultaron más graves de lo previsto '
                . '(muestra: ' . $s4['muestra']['fuerza'] . ').',
                'Revisar caso por caso con quien evaluó, no ajustar la matriz de golpe: con esta muestra el dato orienta, '
                . 'no concluye. Si el patrón se repite en el siguiente proyecto, entonces sí toca mover la metodología.');
        }

        if ($s6['acciones']['vencidas'] > 0) {
            $r[] = self::reco('corto', 'Cerrar las acciones correctivas vencidas',
                $s6['acciones']['vencidas'] . ' acciones seguían abiertas después de su fecha compromiso al cierre del periodo.',
                'Una acción vencida al wrap no se cierra sola: se hereda al siguiente proyecto o se pierde. Asignarles '
                . 'responsable y fecha antes de liberar al equipo.');
        }

        if ($s6['juntas']['realizadas'] > 0 && ! $s6['juntas']['temas']) {
            $r[] = self::reco('corto', 'Registrar el tema de cada junta de seguridad',
                'Se declararon ' . $s6['juntas']['realizadas'] . ' juntas realizadas y ninguna dejó registrado su tema.',
                'La junta sin tema prueba que hubo reunión, no de qué se habló. En una investigación posterior, lo que '
                . 'se pregunta es si ese riesgo concreto se comunicó al crew antes del accidente; el catálogo de temas '
                . 'ya existe en el formulario.');
        }

        if ($s6['juntas']['sin_declarar'] > 0) {
            $r[] = self::reco('corto', 'Declarar la junta de seguridad todos los días',
                $s6['juntas']['sin_declarar'] . ' de ' . $s6['juntas']['dias'] . ' días no declararon si hubo junta.',
                'Ante una auditoría, "sin declarar" no se defiende igual que "no se realizó por tal razón". El sistema '
                . 'admite las dos respuestas; lo que no admite defensa es el silencio.');
        }

        if ($s6['lesiones_pendientes_notificar'] > 0) {
            $r[] = self::reco('corto', 'Confirmar la notificación a la autoridad',
                $s6['lesiones_pendientes_notificar'] . ' accidentes registrables no tienen registrada la notificación a la autoridad.',
                'Verificar si se notificó y no se capturó, o si no se notificó. El primer caso es un hueco de registro; '
                . 'el segundo es un incumplimiento con plazo legal.');
        }

        if ($s3['persona_dia'] > 0) {
            $r[] = self::reco('medio', 'Registrar horas trabajadas, no sólo cabezas',
                'Las tasas de este documento van por persona-día porque no hay horas capturadas.',
                'Con horas se puede calcular la TRIR de OSHA, que es la cifra que pide un estudio y la única comparable '
                . 'entre productoras. Hoy este reporte declara que no puede calcularla.');
        }

        if ($s3['registrables'] > 0) {
            $r[] = self::reco('largo', 'Dar seguimiento a los accidentes registrables',
                $s3['registrables'] . ' accidentes registrables, con ' . $s3['dias_ausencia'] . ' días de ausencia y '
                . $s3['dias_restringido'] . ' de trabajo restringido.',
                'Estos casos son la línea base contra la que se va a medir el siguiente proyecto. Conservar el expediente '
                . 'completo: es lo que pide una auditoría y lo que sostiene una defensa.');
        }

        $r[] = self::reco('largo', 'Comparar entre producciones, no dentro de una',
            'Este reporte cubre ' . $s1['dias_trabajados'] . ' días trabajados y ' . $s4['muestra']['n_eventos']
            . ' eventos cruzables — una sola producción.',
            'El valor real del contraste predicho-vs-real aparece al acumular proyectos: ahí sí se puede afirmar si la '
            . 'metodología de evaluación está calibrada. Conservar los wraps sellados de cada producción.');

        return ['recomendaciones' => $r];
    }

    private static function reco($plazo, $titulo, $evidencia, $accion)
    {
        return ['plazo' => $plazo, 'titulo' => $titulo, 'evidencia' => $evidencia, 'accion' => $accion];
    }

    // -----------------------------------------------------------------------------------------
    // AUXILIARES
    // -----------------------------------------------------------------------------------------

    /** Nombre en español de los eventos citados. Una consulta para todos, no una por fila. */
    private static function nombresDeEventos($ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (! $ids || ! Schema::hasTable('hazard_events')) {
            return [];
        }
        return HazardEvent::whereIn('id', $ids)->pluck('name_es', 'id')->all();
    }

    /** Nivel de riesgo (cualquiera de las dos notaciones) → 1..4, o null si no se reconoce. */
    private static function score($v)
    {
        if ($v === null || $v === '') { return null; }
        $k = strtoupper(trim(self::sinAcentos((string) $v)));
        return isset(self::NIVELES[$k]) ? self::NIVELES[$k] : null;
    }

    /** 1..4 → etiqueta canónica, o null. */
    private static function label($n)
    {
        return ($n && isset(self::NIVEL_LABEL[$n])) ? self::NIVEL_LABEL[$n] : null;
    }

    /**
     * Estado de una acción, unificando las notaciones que conviven en la base.
     *
     * `action_status` guarda 'open', 'Abierto', 'closed', 'Cerrado' y cadena vacía, según la época
     * y el formulario que la escribió. Contarlas por separado partiría el mismo hecho en cuatro
     * columnas y el reporte diría que hay más estados de los que existen.
     */
    private static function estado($v)
    {
        $k = strtolower(trim(self::sinAcentos((string) $v)));
        if ($k === '') { return null; }
        if (strpos($k, 'cerr') === 0 || $k === 'closed') { return 'cerrado'; }
        if (strpos($k, 'abier') === 0 || $k === 'open' || $k === 'in_progress') { return 'abierto'; }
        return $k;
    }

    /** Locación normalizada para comparar: minúsculas, sin acentos, espacios colapsados. */
    private static function normLoc($v)
    {
        $s = strtolower(trim(self::sinAcentos((string) $v)));
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private static function sinAcentos($s)
    {
        return strtr($s, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        ]);
    }

    /** Texto de campo libre: se recorta y se normaliza el vacío a null. Nunca se trunca contenido. */
    private static function limpia($v)
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $v));
        return $s === '' ? null : $s;
    }
}
