<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\cmedic;
use App\Models\MaterialityPhoto;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * MedicalReportController — reportes del módulo de consultas médicas.
 *  - weekly()/weeklyPdf(): BITÁCORA médica semanal (semi-pública) estilo "medical log",
 *    agrupada por día. Gate permission:medical.view.
 *  - materials()/materialsPdf(): CONTEO de medicamentos (uso INTERNO: producción + salud
 *    y seguridad, presupuesto/materialidad); suma por variante. Gate permission:medical.materials.
 *
 * La "fecha efectiva" de una consulta = consultation_date, o DATE(created_at) si es una
 * consulta vieja sin la columna nueva. Los medicamentos se leen del snapshot estructurado
 * `medication_items` (JSON); si una consulta vieja no lo tiene, cae al texto `medication`.
 */
class MedicalReportController extends Controller
{
    private $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    private $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                      'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // ---- BITÁCORA (semi-pública) -------------------------------------------------

    public function weekly(Request $request)
    {
        return view('medical.bitacora', $this->weeklyData($request));
    }

    public function weeklyPdf(Request $request)
    {
        $this->guardLogExport();
        // El PDF es el DOCUMENTO: va completo, sin el acotamiento por departamento de la pantalla
        // (item 3). Quien lo emite es médico o key medic — figuras que ven toda la operación.
        $data = $this->weeklyData($request, false);
        $pdf = Pdf::loadView('medical.bitacora_pdf', $data)->setPaper('a4', 'landscape');
        return $pdf->download('bitacora-medica-' . $data['from'] . '_' . $data['to'] . '.pdf');
    }

    // ---- CONTEO / MATERIALIDAD (interno) -----------------------------------------

    public function materials(Request $request)
    {
        return view('medical.materials', $this->materialsData($request));
    }

    public function materialsPdf(Request $request)
    {
        // SIN guard de emisión (2026-07-24 · PASO 3/3, item 4): el CONTEO son métricas y
        // materialidad — cantidades por variante, SIN diagnósticos ni nombres. Lo exporta quien
        // tenga permiso de verlo (`medical.materials`: super-admin, line-producer, safety-officer,
        // medic). El candado de emisión es sólo para la BITÁCORA, que sí lleva el log clínico.
        $data = $this->materialsData($request);
        $pdf = Pdf::loadView('medical.materials_pdf', $data)->setPaper('a4', 'portrait');
        return $pdf->download('conteo-medicamentos-' . $data['from'] . '_' . $data['to'] . '.pdf');
    }

    /**
     * (2026-07-24 · PASO 3/3, item 4) EMISIÓN DEL LOG CLÍNICO (bitácora).
     *
     * DOS documentos, DOS reglas. La bitácora lleva DIAGNÓSTICOS por persona: se emite para
     * mandarla al estudio o cuando hay tratamiento adicional, así que sólo la exportan el MÉDICO
     * y el KEY MEDIC. Line-producer, coordinador y safety-officer pueden VERLA en pantalla (con
     * su alcance) pero NO emitirla. El conteo de medicamentos no pasa por aquí.
     *
     * @return void
     */
    private function guardLogExport()
    {
        abort_unless($this->canEmitLog(), 403,
            'Sólo un médico o el key medic pueden emitir la bitácora clínica.');
    }

    /**
     * FUENTE ÚNICA de "¿puede emitir la bitácora?" — la usan el guard de arriba Y la vista
     * (variable $canExportLog) para que el botón nunca ofrezca algo que el backend va a rechazar.
     * can() incluye al super-admin (Gate::before) y a los permisos DIRECTOS de Spatie.
     *
     * @return bool
     */
    private function canEmitLog()
    {
        $user = auth()->user();
        return $user ? ($user->isClinician() || $user->can('medical.consolidate')) : false;
    }

    // ---- MATERIALIDAD (evidencia fiscal SAT) ------------------------------------
    // Repositorio de fotos con FECHA DE SERVIDOR (created_at, no editable) como comprobación
    // fiscal del consumo. Mismo gate que el conteo (permission:medical.materials). Degrada con
    // empty-state si la tabla materiality_photos aún no existe.

    public function materiality(Request $request)
    {
        return view('medical.materiality', $this->materialityData($request));
    }

    public function materialityStore(Request $request)
    {
        if (! Schema::hasTable('materiality_photos')) {
            return back()->with('error', 'La materialidad aún no está disponible (falta aplicar la tabla).');
        }

        $request->validate([
            'photos'   => 'required|array|min:1',
            'photos.*' => 'mimes:jpg,jpeg,png,gif,bmp,svg,webp,heic,heif|heic_ok|max:12288', // 12 MB por foto (mismo límite que Accidentes)
            'note'     => 'nullable|string|max:500',
        ], [], [
            'photos'   => 'fotos',
            'photos.*' => 'foto',
        ]);

        $note  = trim((string) $request->input('note'));
        $note  = $note !== '' ? $note : null;
        $count = 0;

        foreach ($request->file('photos') as $image) {
            // HEIC (iPhone) → JPEG si el servidor puede convertir; si no, la validación ya lo rechazó.
            $image = \App\Support\ImageCompressor::normalizeForUpload($image);
            // Nombre único (time()+uniqid()) — mismo patrón que Accidentes/Actos inseguros.
            $filename = time() . '_' . uniqid() . '_mat.' . \App\Support\ImageCompressor::safeExtensionOrBin($image);
            $path = $image->storeAs('materiality_images', $filename, 'public');
            // created_at lo pone Eloquent (servidor) → fecha fiscal no editable.
            MaterialityPhoto::create([
                'image_path'     => Storage::url($path),
                'note'           => $note,
                'captured_by_id' => auth()->id(),
            ]);
            $count++;
        }

        return redirect()->route('medical.materiality')
            ->with('success', $count . ' evidencia(s) de materialidad guardada(s).');
    }

    public function materialityPdf(Request $request)
    {
        $data = $this->materialityData($request, true);
        $pdf = Pdf::loadView('medical.materiality_pdf', $data)->setPaper('a4', 'portrait');
        return $pdf->download('materialidad-' . $data['from'] . '_' . $data['to'] . '.pdf');
    }

    // ---- Datos ------------------------------------------------------------------

    /**
     * @param  bool  $scoped  true = acota por departamento del que mira (VISTA ONLINE, item 3).
     *                        false = documento completo (PDF).
     */
    private function weeklyData(Request $request, $scoped = true)
    {
        [$from, $to] = $this->resolveRange($request);
        $medics    = $this->resolveMedics($request);
        $consultas = $this->fetchConsultas($from, $to, $medics, $scoped);

        $byDay = [];
        foreach ($consultas as $c) {
            $d   = $this->effectiveDate($c);
            $key = $d->toDateString();
            if (! isset($byDay[$key])) {
                $byDay[$key] = ['label' => $this->dayLabel($d), 'rows' => []];
            }
            // Nombre y departamento: el crew sale de users; el paciente LITE (no-crew) sale de
            // lite_patients y NO tiene departamento — se agrupa como "Sin departamento asignado".
            // El nombre del lite SE IMPRIME; nunca queda en blanco (por eso el ?? y el fallback).
            $isLite   = ($c->id_user === null);
            $crewName = trim($c->u_name . ' ' . $c->u_lname . ' ' . $c->u_lname2);
            $rowName  = $crewName !== '' ? $crewName : trim((string) ($c->lite_name ?? ''));
            $byDay[$key]['rows'][] = [
                'name'         => $rowName !== '' ? $rowName : '(sin nombre)',
                'department'   => $isLite ? 'Sin departamento asignado' : $c->u_dept,
                'diagnosis'    => $c->diagnosis,
                'medications'  => $this->medsText($c),
                'management'   => $this->mgmtText($c),
                'observations' => $c->observations,
            ];
        }
        ksort($byDay);

        return [
            'byDay'          => $byDay,
            'rangeLabel'     => $this->rangeLabel($from, $to),
            'from'           => $from->toDateString(),
            'to'             => $to->toDateString(),
            'totalConsultas' => $consultas->count(),
        ] + $this->medicFilterData($medics);
    }

    private function materialsData(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $medics    = $this->resolveMedics($request);
        $consultas = $this->fetchConsultas($from, $to, $medics);

        $agg = [];
        foreach ($consultas as $c) {
            $items = $c->medication_items;
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $it) {
                $name = trim((string) ($it['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $dosage = trim((string) ($it['dosage'] ?? ''));
                $pres   = trim((string) ($it['presentation'] ?? ''));
                $qty    = (float) ($it['quantity'] ?? 0);
                $key    = mb_strtolower($name . '|' . $dosage . '|' . $pres);
                if (! isset($agg[$key])) {
                    $agg[$key] = [
                        'name'         => $name,
                        'dosage'       => $dosage,
                        'presentation' => $pres,
                        'label'        => trim($name . ' ' . $dosage . ($pres ? ' (' . $pres . ')' : '')),
                        'quantity'     => 0,
                    ];
                }
                $agg[$key]['quantity'] += $qty;
            }
        }

        $totals = array_values($agg);
        usort($totals, function ($a, $b) {
            return strcmp($a['name'], $b['name']) ?: strcmp($a['dosage'], $b['dosage']);
        });
        // Normalizar cantidades enteras (2.0 → 2) para mostrar limpio.
        foreach ($totals as &$t) {
            if ($t['quantity'] == (int) $t['quantity']) {
                $t['quantity'] = (int) $t['quantity'];
            }
        }
        unset($t);

        return [
            'totals'         => $totals,
            'rangeLabel'     => $this->rangeLabel($from, $to),
            'from'           => $from->toDateString(),
            'to'             => $to->toDateString(),
            'totalConsultas' => $consultas->count(),
            'distinctMeds'   => count($totals),
        ] + $this->medicFilterData($medics);
    }

    /**
     * Datos de la materialidad para el rango. Reusa resolveRange (mismo filtro que el conteo).
     * $forPdf precalcula data-URIs base64 (dompdf no resuelve /storage/ relativo ni remoto).
     */
    private function materialityData(Request $request, $forPdf = false)
    {
        [$from, $to] = $this->resolveRange($request);

        $tableReady = Schema::hasTable('materiality_photos');
        $photos = collect();
        if ($tableReady) {
            $photos = MaterialityPhoto::query()
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        $items = [];
        if ($forPdf) {
            foreach ($photos as $p) {
                $items[] = [
                    'note'       => $p->note,
                    'created_at' => $p->created_at,
                    'data_uri'   => $this->photoDataUri($p->image_path),
                ];
            }
        }

        return [
            'photos'      => $photos,
            'items'       => $items,
            'tableReady'  => $tableReady,
            'rangeLabel'  => $this->rangeLabel($from, $to),
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'totalPhotos' => $photos->count(),
        ];
    }

    /** Ruta pública (Storage::url) → data-URI base64 para incrustar en el PDF (dompdf). */
    private function photoDataUri($imagePath)
    {
        try {
            $rel = ltrim(preg_replace('#^/storage/#', '', parse_url((string) $imagePath, PHP_URL_PATH) ?: (string) $imagePath), '/');
            if ($rel !== '' && Storage::disk('public')->exists($rel)) {
                $bytes = Storage::disk('public')->get($rel);
                $ext   = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                $mime  = ($ext === 'png') ? 'image/png'
                       : (($ext === 'gif') ? 'image/gif'
                       : (($ext === 'webp') ? 'image/webp' : 'image/jpeg'));
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        } catch (\Throwable $e) {
            // sin data-uri → el PDF muestra un marcador de "imagen no disponible".
        }
        return null;
    }

    // ---- Helpers ----------------------------------------------------------------

    /** Rango [from,to]; default = semana actual (lunes a domingo). */
    private function resolveRange(Request $request)
    {
        $today = Carbon::today();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : $today->copy()->startOfWeek(Carbon::MONDAY);
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : $from->copy()->addDays(6)->endOfDay();
        if ($to->lt($from)) {
            $to = $from->copy()->endOfDay();
        }
        return [$from, $to];
    }

    /**
     * (2026-07-24 · PASO 2/3, item 4) Médicos seleccionados en el filtro (?medics[]=id).
     * Vacío = TODOS (default). Es un filtro de CONSULTA, no de permiso: quien ya puede ver el
     * agregado lo filtra como quiera. Sirve para separar métricas y evaluar el impacto de las
     * consultas por médico según lo que necesite cada producción.
     *
     * @return array<int>
     */
    private function resolveMedics(Request $request)
    {
        $raw = $request->input('medics', []);
        if (! is_array($raw)) {
            $raw = [$raw];
        }
        $ids = [];
        foreach ($raw as $v) {
            $v = (int) $v;
            if ($v > 0) {
                $ids[] = $v;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Opciones del filtro + etiqueta legible de lo aplicado (se imprime en el PDF para que el
     * entregable sea honesto sobre qué contiene). Las opciones salen de los AUTORES REALES de
     * consultas — no del catálogo de médicos — para no ofrecer filtros que no devuelven nada.
     * Prefiere el SNAPSHOT congelado (medic_name) sobre el nombre en vivo del usuario.
     *
     * @param  array<int>  $selected
     * @return array
     */
    private function medicFilterData(array $selected)
    {
        $options = cmedic::query()
            ->leftJoin('users', 'cmedic.created_by_id', '=', 'users.id')
            ->whereNotNull('cmedic.created_by_id')
            ->groupBy('cmedic.created_by_id', 'users.name', 'users.lname', 'users.lname2')
            ->orderBy('users.name')
            ->get([
                'cmedic.created_by_id as id',
                'users.name as u_name',
                'users.lname as u_lname',
                'users.lname2 as u_lname2',
            ])
            ->map(function ($r) {
                $nombre = trim($r->u_name . ' ' . $r->u_lname . ' ' . $r->u_lname2);
                return ['id' => (int) $r->id, 'name' => $nombre !== '' ? $nombre : 'Médico #' . $r->id];
            })
            ->values();

        $label = 'Todos los médicos';
        if (count($selected)) {
            $names = $options->whereIn('id', $selected)->pluck('name')->all();
            $label = $names ? implode(' · ', $names) : 'Selección sin atenciones';
        }

        return [
            'medicOptions'    => $options,
            'selectedMedics'  => $selected,
            'medicsLabel'     => $label,
            // Sólo aplica a la BITÁCORA. El conteo lo exporta quien pueda verlo (item 4).
            'canExportLog'    => $this->canEmitLog(),
        ];
    }

    /**
     * @param  array<int>  $medicIds  vacío = todos los médicos (los AGREGADOS nunca se aíslan por
     *                                autor: si no concentraran a todos, el entregable se rompe).
     * @param  bool        $scoped    acotar por departamento del que mira (item 3).
     */
    private function fetchConsultas(Carbon $from, Carbon $to, array $medicIds = [], $scoped = false)
    {
        $q = cmedic::query()
            ->leftJoin('users', 'cmedic.id_user', '=', 'users.id')
            ->select(
                'cmedic.*',
                'users.name as u_name',
                'users.lname as u_lname',
                'users.lname2 as u_lname2',
                'users.puestodepartamento as u_dept'
            )
            ->whereRaw('COALESCE(cmedic.consultation_date, DATE(cmedic.created_at)) BETWEEN ? AND ?',
                [$from->toDateString(), $to->toDateString()])
            ->when(count($medicIds) > 0, function ($q) use ($medicIds) {
                // Con filtro activo, las consultas históricas SIN autor quedan fuera: no hay
                // médico al que atribuirlas. Sin filtro (default) sí entran.
                $q->whereIn('cmedic.created_by_id', $medicIds);
            })
            ->orderByRaw('COALESCE(cmedic.consultation_date, DATE(cmedic.created_at)) ASC')
            ->orderBy('cmedic.id_cmedic', 'asc');

        // (2026-07-25) PACIENTES LITE (no-crew): su nombre vive en lite_patients, no en users. Sin
        // este join la bitácora y el conteo los dejaban en blanco (id_user NULL → columnas users
        // NULL). Guard de esquema para instancias sin el delta lite aplicado.
        if (Schema::hasColumn('cmedic', 'lite_patient_id') && Schema::hasTable('lite_patients')) {
            $q->leftJoin('lite_patients', 'cmedic.lite_patient_id', '=', 'lite_patients.id')
              ->addSelect('lite_patients.full_name as lite_name');
        }

        // (2026-07-24 · PASO 3/3, item 3) ALCANCE POR DEPARTAMENTO en la VISTA ONLINE. Cierra el
        // hueco que quedó del 1/3: el expediente individual ya estaba acotado, pero la bitácora
        // mostraba el log clínico de TODOS los departamentos, así que un HOD con medical.view lo
        // veía completo y puenteaba el candado. Misma fuente única que el expediente
        // (applyDepartmentScope, que correlaciona contra `users.id` — por eso va DESPUÉS del join).
        // Quien tiene crew.view.all-departments (super-admin, coordinador, line-producer, medic,
        // safety-officer) no se ve afectado: el helper devuelve la consulta intacta.
        if ($scoped && auth()->user()) {
            $viewer = auth()->user();
            if (! $viewer->can('crew.view.all-departments')) {
                // Acota el CREW por departamento, PERO nunca desaparece a los pacientes LITE
                // (id_user NULL): no tienen departamento por definición, así que el scope los
                // borraría. Se conservan y en la vista se agrupan como "Sin departamento asignado".
                // El `orWhereNull` va DENTRO del grupo para que quede (scope) OR (es lite).
                $q->where(function ($sub) use ($viewer) {
                    \App\Models\User::applyDepartmentScope($sub, $viewer);
                    $sub->orWhereNull('cmedic.id_user');
                });
            }
            // Con crew.view.all-departments (super-admin, coordinador, line-producer, medic,
            // safety-officer) no se acota: ve crew + lite completos (misma consulta que el PDF).
        }

        return $q->get();
    }

    private function effectiveDate($c)
    {
        return $c->consultation_date
            ? Carbon::parse($c->consultation_date)
            : Carbon::parse($c->created_at);
    }

    /**
     * Medicamentos legibles del snapshot estructurado; cae al texto viejo si no hay.
     *
     * (2026-07-24 · PASO 3/3, item 2) Si NO hubo medicamento, devuelve el MANEJO. Sin esto, una
     * valoración o un reposo salen como renglón vacío en la bitácora y desaparecen de cualquier
     * lectura epidemiológica: 5 personas valoradas por calor y mandadas a la sombra sin medicar
     * no aparecerían en ningún lado.
     */
    private function medsText($c)
    {
        $items = $c->medication_items;
        if (is_array($items) && count($items)) {
            $lines = [];
            foreach ($items as $it) {
                $q = $it['quantity'] ?? 1;
                $q = ($q == (int) $q) ? (int) $q : $q;
                $extra = array_filter([
                    $it['dosage'] ?? '',
                    ($it['presentation'] ?? '') !== '' ? '(' . $it['presentation'] . ')' : '',
                ]);
                $lines[] = trim($q . ' ' . ($it['name'] ?? '') . ' ' . implode(' ', $extra));
            }
            return implode("\n", $lines);
        }
        $texto = trim((string) ($c->medication ?? ''));
        if ($texto !== '') {
            return $texto;
        }
        $mgmt = $c->managementLabels();
        return $mgmt ? implode(' · ', $mgmt) : '';
    }

    /** Manejo/conducta legible, para pintarlo aparte cuando SÍ hubo medicamento. */
    private function mgmtText($c)
    {
        $mgmt = $c->managementLabels();
        return $mgmt ? implode(' · ', $mgmt) : '';
    }

    private function dayLabel(Carbon $d)
    {
        return $this->dias[$d->dayOfWeek] . ', ' . $d->day . ' de ' . $this->meses[$d->month] . ' ' . $d->year;
    }

    private function rangeLabel(Carbon $from, Carbon $to)
    {
        return 'Del ' . $from->day . ' de ' . $this->meses[$from->month]
             . ' al ' . $to->day . ' de ' . $this->meses[$to->month] . ', ' . $to->year;
    }
}
