<?php

namespace App\Http\Controllers;

use App\Models\InjuryReport;
use App\Models\hazardnotification;
use App\Models\unsafecond;
use App\Models\cmedic;
use App\Models\DailyReport;
use App\Models\ScoutingReport;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * HomeController — el TABLERO de quien entra a la app.
 *
 * (2026-07-24) Dos cambios de fondo pedidos por el owner:
 *
 *  1) EL HOME DEL PANEL DEJA DE SER EL PERFIL. La vista decidía con `users.view` si pintaba
 *     tablero o tarjeta de perfil, y ese permiso no lo tiene el médico: un Médico de Set entraba
 *     y veía su propia foto y su cuestionario, o sea lo mismo que ya tiene en /perfil. Ahora
 *     decide canSeePanel() —la MISMA fuente única que abre el sidebar—, así que quien tiene
 *     panel ve tablero y sólo el crew raso ve la tarjeta.
 *
 *  2) DATA VIVA, NO DE ADORNO. Los sparklines eran series inventadas y "días de rodaje" era una
 *     constante de abril de 2025 escrita a mano. Aquí ya no hay número que no salga de la base.
 *     Cuando un dato todavía no existe se devuelve vacío y la vista dice que está vacío; nunca
 *     se rellena con algo verosímil.
 *
 * ALCANCE: cada bloque se calcula SÓLO si quien mira puede abrir la pantalla que lo detalla. Un
 * tablero no puede ser el atajo para ver lo que la pantalla de destino te negaría — y de paso
 * no se consulta la base para pintar tarjetas que no se van a mostrar.
 */
class HomeController extends Controller
{
    /** Ventana del tablero. 8 semanas: suficiente para que una tendencia se note. */
    const WEEKS = 8;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = auth()->user();

        $canInjury  = $user && $user->can('injury.view');
        $canLoc     = $user && $user->can('locations.view');
        $canDsr     = $user && $user->can('dsr.view');
        $canHaz     = $user && $user->can('hazards.view');
        $canMedical = $user && $user->can('medical.view');
        $canMeds    = $user && $user->can('medical.materials');

        // ---- KPIs (cada uno tras su permiso) ------------------------------------------------
        $totalAccidents = $canInjury ? InjuryReport::count() : 0;
        $lastAccident   = $canInjury ? InjuryReport::latest('incident_date')->first() : null;
        // Carbon 3 (upgrade L13) cambió diffInDays: ahora devuelve FLOAT y CON SIGNO. Sin
        // normalizar, "Días sin accidentes" salía con decimales (p. ej. 0.47 si el accidente
        // es de hoy) o incluso negativo. Se fuerza a entero absoluto de días completos.
        $daysSinceLastAccident = $lastAccident
            ? (int) floor(abs(Carbon::parse($lastAccident->incident_date)->diffInDays(Carbon::now())))
            : 'N/A';

        // (2026-07-24 · PASO 2/3, item 2) El KPI cuenta consultas INDIVIDUALES, así que respeta el
        // aislamiento por médico: un médico ve su propio total, no el global. Los agregados que SÍ
        // concentran a todos son la bitácora y el conteo de medicamentos.
        $totalMedicalConsults = $canMedical ? cmedic::visibleTo($user)->count() : 0;

        $hasScouting = Schema::hasTable('scouting_reports');
        $totalLocationReports = ($canLoc && $hasScouting) ? ScoutingReport::count() : 0;

        $productionDays = $canDsr ? $this->productionDays() : 0;
        $shootingDays   = $canDsr ? $this->shootingDays() : 0;

        // ---- Series REALES de las últimas 8 semanas (sparklines) ----------------------------
        $sparkAccidents = $canInjury ? $this->weeklyCounts(InjuryReport::pluck('incident_date')) : [];
        $sparkConsults  = $canMedical
            ? $this->weeklyCounts(
                cmedic::visibleTo($user)->get(['cmedic.consultation_date', 'cmedic.created_at'])
                    ->map(function ($c) { return $c->consultation_date ?: $c->created_at; })
            )
            : [];
        $sparkLocations = ($canLoc && $hasScouting) ? $this->weeklyCounts(ScoutingReport::pluck('created_at')) : [];
        $sparkShooting  = $canDsr ? $this->weeklyCounts(DailyReport::pluck('report_date')) : [];

        // ---- Gráfica de actos/condiciones (existente, ahora tras hazards.view) ---------------
        $weeklyUnsafeReports = $canHaz ? $this->weeklyUnsafeReports() : [];

        // ---- Panel médico con datos reales ---------------------------------------------------
        $medical = $canMedical ? $this->medicalPanel($user, $canMeds) : null;

        // ---- Calendario de producción (2026-07-24) -------------------------------------------
        // Dónde estamos HOY según la regla derivada, y cuánto falta al wrap preestablecido.
        // Va tras `dsr.view` porque su fuente son los reportes diarios. Si la producción no tiene
        // fechas todavía, `countdown` es null y la vista no inventa un wrap que nadie fijó.
        $calendario = null;
        if ($canDsr) {
            $prod = \App\Support\CurrentProduction::get();
            $calendario = [
                'hoy'        => \App\Support\ProductionCalendar::todayLabel(),
                'en_rodaje'  => \App\Support\ProductionCalendar::inShoot(),
                'countdown'  => \App\Support\ProductionCalendar::countdown(),
                'produccion' => $prod ? $prod->name : null,
            ];
        }

        return view('inicio', [
            'calendario'            => $calendario,
            'totalAccidents'        => $totalAccidents,
            'daysSinceLastAccident' => $daysSinceLastAccident,
            'totalMedicalConsults'  => $totalMedicalConsults,
            'totalLocationReports'  => $totalLocationReports,
            'productionDays'        => $productionDays,
            'shootingDays'          => $shootingDays,
            'weeklyUnsafeReports'   => $weeklyUnsafeReports,
            'sparkAccidents'        => $sparkAccidents,
            'sparkConsults'         => $sparkConsults,
            'sparkLocations'        => $sparkLocations,
            'sparkShooting'         => $sparkShooting,
            'medical'               => $medical,
            'canSee'                => [
                'injury'  => $canInjury,
                'loc'     => $canLoc,
                'dsr'     => $canDsr,
                'haz'     => $canHaz,
                'medical' => $canMedical,
                'meds'    => $canMeds,
            ],
        ]);
    }

    /**
     * PANEL MÉDICO — atenciones y medicamentos de las últimas 8 semanas.
     *
     * ALCANCE: se aplica visibleTo (propiedad), igual que el KPI. Un médico ve SU carga; el
     * super-admin, el key medic y quien no atiende ven la operación completa.
     *
     * NO se aplica el acotamiento por departamento que sí lleva la bitácora en pantalla, y la
     * razón es que aquí no hay ni una fila por persona: son conteos y totales, sin nombre, sin
     * diagnóstico y sin observaciones. Lo que la bitácora protege —el log clínico— no está.
     *
     * Los MEDICAMENTOS van aparte, tras `medical.materials`: es el mismo permiso que abre el
     * conteo. Un HOD tiene medical.view pero no ése, y el tablero no puede enseñarle un agregado
     * que la pantalla de destino le negaría.
     *
     * @param  \App\Models\User  $user
     * @param  bool  $canMeds
     * @return array
     */
    private function medicalPanel($user, $canMeds)
    {
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);
        $hoy  = Carbon::now()->toDateString();

        $rows = cmedic::visibleTo($user)
            ->whereRaw('COALESCE(cmedic.consultation_date, DATE(cmedic.created_at)) >= ?', [$from->toDateString()])
            ->get([
                'cmedic.id_cmedic', 'cmedic.id_user', 'cmedic.consultation_date', 'cmedic.created_at',
                'cmedic.medication_items', 'cmedic.management', 'cmedic.without_record',
            ]);

        $personas = [];
        $fechas   = [];
        $hoyCount = 0;
        $sinExp   = 0;
        $meds     = [];
        $manejo   = [];

        foreach ($rows as $c) {
            $fecha = $c->consultation_date
                ? Carbon::parse($c->consultation_date)
                : Carbon::parse($c->created_at);
            $fechas[] = $fecha->toDateString();
            if ($fecha->toDateString() === $hoy) {
                $hoyCount++;
            }
            $personas[(int) $c->id_user] = true;

            // (PASO 3/3, item 1) Consulta atendida SIN expediente. No es una falla del médico —
            // nunca se niega la atención por papeleo—: es la persona a la que hay que perseguirle
            // el cuestionario, y por eso el número vive en el tablero y no en un log.
            if ((int) $c->without_record === 1) {
                $sinExp++;
            }

            $items = $c->medication_items;
            if (is_array($items)) {
                foreach ($items as $it) {
                    $name = trim((string) (isset($it['name']) ? $it['name'] : ''));
                    if ($name === '') {
                        continue;
                    }
                    $dosage = trim((string) (isset($it['dosage']) ? $it['dosage'] : ''));
                    $key    = mb_strtolower($name . '|' . $dosage);
                    if (! isset($meds[$key])) {
                        $meds[$key] = ['label' => trim($name . ' ' . $dosage), 'quantity' => 0];
                    }
                    $meds[$key]['quantity'] += (float) (isset($it['quantity']) ? $it['quantity'] : 0);
                }
            }

            // El MANEJO es lo que vuelve contable a la consulta SIN medicamento. Sin él, atender a
            // alguien y mandarlo a reposo no dejaba rastro medible en ningún tablero.
            $labels = method_exists($c, 'managementLabels') ? $c->managementLabels() : [];
            foreach ((array) $labels as $lbl) {
                $lbl = trim((string) $lbl);
                if ($lbl === '') {
                    continue;
                }
                $manejo[$lbl] = (isset($manejo[$lbl]) ? $manejo[$lbl] : 0) + 1;
            }
        }

        // Top de medicamentos por cantidad; el empate se resuelve por nombre para que el orden
        // no baile entre recargas.
        $topMeds = [];
        if ($canMeds) {
            $topMeds = array_values($meds);
            usort($topMeds, function ($a, $b) {
                if ($a['quantity'] == $b['quantity']) {
                    return strcmp($a['label'], $b['label']);
                }
                return $a['quantity'] < $b['quantity'] ? 1 : -1;
            });
            foreach ($topMeds as &$m) {
                if ($m['quantity'] == (int) $m['quantity']) {
                    $m['quantity'] = (int) $m['quantity'];
                }
            }
            unset($m);
            $topMeds = array_slice($topMeds, 0, 6);
        }

        arsort($manejo);

        // ¿Este tablero está viendo sólo lo propio? Se dice en pantalla: un médico que lee
        // "12 atenciones" tiene que saber si son las suyas o las de la producción entera.
        $propio = $user && $user->isClinician() && ! $user->can('medical.consolidate');

        return [
            'consultas'   => $rows->count(),
            'hoy'         => $hoyCount,
            'personas'    => count($personas),
            'sin_exp'     => $sinExp,
            'top_meds'    => $topMeds,
            'manejo'      => $manejo,
            'serie'       => $this->weeklyCounts($fechas),
            'ultima'      => count($fechas) ? Carbon::parse(max($fechas)) : null,
            'solo_propio' => $propio,
            'puede_meds'  => $canMeds,
            'desde'       => $from->copy(),
        ];
    }

    /**
     * Cuenta cuántos elementos caen en cada una de las últimas WEEKS semanas (lunes a domingo).
     *
     * Devuelve [] si TODO da cero: una línea plana pegada al fondo no es un dato, es ruido, y la
     * vista prefiere no dibujar sparkline a dibujar uno que no dice nada.
     *
     * @param  iterable  $dates  fechas (string, Carbon o null)
     * @return array<int>
     */
    private function weeklyCounts($dates)
    {
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);
        $serie = array_fill(0, self::WEEKS, 0);
        $algo  = false;

        foreach ($dates as $d) {
            if (! $d) {
                continue;
            }
            try {
                $c = Carbon::parse($d)->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable $e) {
                continue;
            }
            // round() y no floor(): el cambio de horario de verano deja semanas de 6.96 días y
            // floor() las mandaría al bucket anterior.
            $idx = (int) round($start->diffInDays($c, false) / 7);
            if ($idx >= 0 && $idx < self::WEEKS) {
                $serie[$idx]++;
                $algo = true;
            }
        }

        return $algo ? $serie : [];
    }

    /**
     * Actos y condiciones inseguras por semana del MES en curso (gráfica de tendencia).
     * Se conserva tal cual estaba; sólo se movió a su propio método.
     *
     * @return array
     */
    private function weeklyUnsafeReports()
    {
        $startDate   = Carbon::now()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $endDate     = Carbon::now()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $currentDate = $startDate->copy();
        $out         = [];

        while ($currentDate->lte($endDate)) {
            $weekStart = $currentDate->toDateString();
            $weekEnd   = $currentDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

            $out[] = [
                'week_start'  => $weekStart,
                'week_end'    => $weekEnd,
                'acts_count'  => hazardnotification::whereBetween('created_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count(),
                'conds_count' => unsafecond::whereBetween('created_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count(),
            ];

            $currentDate->addWeek();
        }

        return $out;
    }

    /**
     * DÍAS DE RODAJE = días distintos en los que se rodó.
     *
     * Historia de este número, porque explica las dos correcciones:
     *   1) Antes devolvía 21 SIEMPRE — dos fechas de abril de 2025 escritas a mano y restadas.
     *      Se veía razonable, que es justo lo que lo hacía peligroso: nadie audita un KPI que
     *      parece correcto.
     *   2) Después contaba fechas distintas PERO filtrando `shoot_day > 0`, y ese filtro se
     *      comía el día que alguien tecleó como 0 (un DSR con 150 personas de crew).
     * Ahora lo cuenta ProductionCalendar, que no depende de un número tecleado.
     *
     * @return int
     */
    private function shootingDays()
    {
        return \App\Support\ProductionCalendar::shootDaysCount();
    }

    /**
     * DÍAS DE PRODUCCIÓN = días TRABAJADOS transcurridos (lunes a sábado; el domingo no cuenta),
     * desde que arrancó la operación —lo primero que haya pasado: un scouting, porque ahí empieza
     * la prep, o el primer reporte diario— hasta el último día con actividad.
     *
     * (2026-07-24) Antes eran días NATURALES. Decisión del owner: los días de una producción no
     * son de calendario, son días trabajados; hay semanas de 5 y de 6. Se homologa a la misma
     * regla que usa la prep para contar hacia atrás, así los dos contadores hablan el mismo
     * idioma. Se cuenta contra el ÚLTIMO día con actividad, no contra hoy: una producción que
     * terminó hace tres meses no lleva tres meses más de producción.
     *
     * @return int
     */
    private function productionDays()
    {
        $inicio = null;

        if (Schema::hasTable('scouting_reports')) {
            $min = ScoutingReport::min('created_at');
            if ($min) {
                $inicio = Carbon::parse($min);
            }
        }

        return \App\Support\ProductionCalendar::workedDaysElapsed($inicio);
    }

}
