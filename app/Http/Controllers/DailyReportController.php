<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\DailyLog;
use App\Models\SafetyStandard;
use App\Models\HazardEvent;
use App\Http\Requests\StoreDailyReportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
// Export a PDF = window.print() del navegador desde la vista `show` (botón "PDF"). Se evaluó y
// DESCARTÓ el PDF de servidor (2026-07-07): dompdf no renderiza el Tailwind/flex de la vista, y
// Browsershot/Chromium daba fallos de fuentes + exigía Node+Chromium+internet en el servidor.
// window.print usa el navegador del usuario → fidelidad total, cero infraestructura.

class DailyReportController extends Controller
{
    /**
     * 1. LISTADO DE REPORTES (Equivalente a tu método inicial())
     */
    public function index()
    {
        // (2026-07-24) Orden por la FECHA DEL DÍA REPORTADO, no por cuándo se capturó.
        // Antes era `latest()`, o sea `created_at DESC`: un reporte que se escribe al día
        // siguiente —o uno viejo que se corrige hoy— se colaba hasta arriba y el listado
        // mostraba los días desordenados (Día 2, 3, 5, 6, 8…). Ahora que el número de día lo
        // DERIVA la fecha, el listado tiene que seguir esa misma fecha o se contradice solo.
        // `id` sólo desempata dos reportes del mismo día.
        $dailyReports = DailyReport::orderBy('report_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);
        return view('admin.dailyreports.index', compact('dailyReports'));
    }

    /**
     * 2. FORMULARIO DE CREACIÓN
     */
    public function create()
    {
        // (2026-07-25) ARRANQUE EN FRÍO: el DSR debe poder crearse completo sin producción
        // configurada, sin crew y sin scouting. El formulario PRELLENA lo derivable y deja
        // capturar lo demás a mano. Se pasa el calendario (ancla + fechas con DSR) para que el
        // espejo en JS re-sugiera el día al vuelo cuando el usuario mueve la fecha.
        $ancla = \App\Support\ProductionCalendar::anchorDate();

        // Día de rodaje EDITABLE, prellenado con el número que deriva HOY (Día N; en arranque en
        // frío —sin ancla ni DSRs previos— 1). El usuario lo confirma o lo ajusta; el store lo RESPETA.
        $derivado = \App\Support\ProductionCalendar::shootDayFor(now());

        // (2026-07-25) CADENA DE ORIGEN DEL HOSPITAL. Se RETIRÓ el arrastre del DSR anterior: heredar
        // el hospital de OTRA locación produce un dato erróneo con apariencia de correcto, y es el
        // campo que alguien lee corriendo. La fuente natural es el SCOUTING de la locación (que ya
        // calculó su hospital privado-primero + ETA con el buscador geo). El form tiene UN SOLO campo
        // de locación con datalist de estas locaciones; al escribir/elegir una reconocida, el servidor
        // amarra el scouting por NOMBRE (StoreDailyReportRequest::prepareForValidation) y hereda
        // hospital/ambulancia (editables). Sin coincidencia → captura manual (arranque en frío). Esta
        // lista alimenta el datalist y el mapa JS; acotada a la producción vigente si la hay, si no,
        // todos los scouting recientes (arranque en frío).
        $scoutings = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('scouting_reports')) {
            $q = \App\Models\ScoutingReport::query()
                ->whereNotNull('location_name')->where('location_name', '!=', '');
            $pid = \App\Support\CurrentProduction::id();
            if ($pid !== null && \Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'production_id')) {
                // Producción vigente: sus locaciones primero. Si no tiene ninguna scouteada,
                // no dejamos el selector vacío — caemos a todas (el arranque en frío lo exige).
                if ((clone $q)->where('production_id', $pid)->exists()) {
                    $q->where('production_id', $pid);
                }
            }
            $scoutings = $q->orderBy('id', 'desc')->limit(100)
                ->get(['id', 'location_name', 'nearest_hospital', 'hospital_eta', 'ambulance_company', 'latitude', 'longitude']);
        }

        // (2026-08-01 · captura fluida) Temas que la locación ya evaluó, para SUGERIRLOS al
        // elegirla (chips que se agregan al campo; nada se agrega solo). Las alertas de
        // producción (permisos/acciones) NO van en el DSR: son de un módulo aparte (DsrContext
        // las conserva). Defensivo: DsrContext no rompe si falta una tabla/modelo.
        $pidCtx = \App\Support\CurrentProduction::id();

        return view('admin.dailyreports.create', [
            'calAncla'     => $ancla ? $ancla->toDateString() : null,
            'calFechas'    => \App\Support\ProductionCalendar::shootDates(),
            'diaLabel'     => \App\Support\ProductionCalendar::labelFor(now()),
            'diaSugerido'  => $derivado !== null ? $derivado : 1,
            'scoutings'    => $scoutings,
            'dsrLocTopics' => \App\Support\DsrContext::locationTopics($pidCtx),
        ]);
    }

    /**
     * 3. GUARDAR EL ENCABEZADO DEL REPORTE
     */
    public function store(StoreDailyReportRequest $request)
    {
        // Las reglas (incluida la obligatoriedad condicional de EPP — módulo 8) viven en
        // StoreDailyReportRequest. validated() ya trae medic_name y los campos nuevos.
        $data = $request->validated();

        // safety_meeting_topics llega como array (checkboxes). La columna es string → lo colapsamos
        // a CSV. array_filter descarta valores vacíos por si acaso; el show reconstituye los chips.
        if (isset($data['safety_meeting_topics']) && is_array($data['safety_meeting_topics'])) {
            $data['safety_meeting_topics'] = implode(', ', array_filter($data['safety_meeting_topics'], function ($t) {
                return trim((string) $t) !== '';
            }));
            // Red de seguridad final: la validación ya limita a 15 temas, pero la columna es
            // varchar(255) con MySQL en modo estricto — un desbordamiento sería un error 1406,
            // no un truncado silencioso. Mejor recortar por la última coma completa que
            // devolverle al usuario una pantalla de error por marcar casillas de más.
            if (mb_strlen($data['safety_meeting_topics']) > 255) {
                $corte = mb_substr($data['safety_meeting_topics'], 0, 255);
                $ultima = mb_strrpos($corte, ',');
                $data['safety_meeting_topics'] = $ultima !== false ? mb_substr($corte, 0, $ultima) : $corte;
            }
        }

        // (2026-07-21) Compresión al subir: ImageCompressor reduce el lado mayor a 1600 px
        // y re-codifica a JPEG q82 (ver el porqué y las mediciones en la clase). Devuelve la
        // MISMA forma de ruta que ->store(), así que Storage::url() y la columna no cambian.
        // Si la compresión no es posible, la propia clase guarda el original: nunca se pierde.
        if ($request->hasFile('hero_image')) {
            $path = \App\Support\ImageCompressor::store($request->file('hero_image'), 'daily_reports/heroes');
            if ($path !== null) {
                $data['hero_image_path'] = Storage::url($path);
            }
        }
        // El guard de columna va ANTES de escribir, no después: en una instancia sin el
        // delta aplicado, el bucle de más abajo descartaría la clave y el archivo quedaría
        // huérfano en disco, sin nada que lo referencie y sin aviso al usuario. (update()
        // ya lo hacía así; aquí estaba al revés.)
        if ($request->hasFile('safety_meeting_photo') && Schema::hasColumn('daily_reports', 'safety_meeting_photo_path')) {
            $path = \App\Support\ImageCompressor::store($request->file('safety_meeting_photo'), 'daily_reports/meetings');
            if ($path !== null) {
                $data['safety_meeting_photo_path'] = Storage::url($path);
            }
        }
        unset($data['hero_image'], $data['safety_meeting_photo']);

        // AUTOFIRMA (sistema cerrado): el autor NO viene del formulario — se fija con el usuario
        // autenticado → no se puede falsear QUIÉN elaboró el reporte. (report_date SÍ es del form:
        // es la fecha del día reportado, no la firma.)
        $data['author_name'] = auth()->user()->name;
        if (Schema::hasColumn('daily_reports', 'created_by_id')) {
            $data['created_by_id'] = auth()->id();
        }

        // (2026-07-25) DÍA DE RODAJE — captura manual PRELLENADA (arranque en frío). resolveShootDay()
        // RESPETA el número tecleado (el formulario lo prellena con la derivación y el usuario lo
        // ajusta); sin número lo DERIVA de la fecha del reporte (primer día con DSR = 1, siguiente
        // distinto = 2…) y, sin ancla ni DSRs previos, 1. La columna se escribe siempre para que
        // producción conserve su etiqueta y para no cambiar el payload de firma de los DSR sellados.
        $data['shoot_day'] = $this->resolveShootDay($data);

        // (2026-07-24) VÍNCULO CON LA PRODUCCIÓN. Estaba en 0 de 8 reportes: la columna existía y
        // nadie la llenaba, así que el reporte de wrap no podía acotar nada. Todo lo NUEVO nace
        // vinculado; lo viejo se queda como está (tocarlo rompería sellos).
        if (Schema::hasColumn('daily_reports', 'production_id') && empty($data['production_id'])) {
            $pid = \App\Support\CurrentProduction::id();
            if ($pid !== null) {
                $data['production_id'] = $pid;
            }
        }

        // (2026-07-12) Columnas nuevas (cimientos módulos 6-14): PROD aún no tiene el SQL, así que
        // solo persistimos las que YA existen en el esquema (guard por columna). Sin esto, insertar
        // 'humidity'/'required_ppe'/etc. en prod tronaría antes de aplicar el SQL. required_ppe y
        // day_risk_factors los castea el modelo a JSON.
        foreach ([
            'humidity', 'wind_speed', 'heat_index', 'required_ppe', 'day_risk_factors', 'uuid',
            // (2026-07-21) delta del safety meeting declarado
            'safety_meeting_held', 'safety_meeting_photo_path',
            // (2026-07-25) vínculo con el scouting de origen del hospital (prod aún sin la columna)
            'scouting_report_id',
        ] as $col) {
            if (array_key_exists($col, $data) && !Schema::hasColumn('daily_reports', $col)) {
                unset($data[$col]);
            }
        }

        $report = DailyReport::create($data);

        // MÓDULO 6 (firma digital / no-repudio): sella el encabezado recién creado con el hash
        // SHA-256 + firmante + metadatos del request. signDocument ya trae guard interno: si la
        // tabla digital_signatures no existe en prod, es no-op (regresa null) y nada truena.
        //
        // refresh() ANTES de firmar es CLAVE: columnas decimales (wind_speed/heat_index) llegan
        // como float en memoria (41.2) pero como string normalizada desde MySQL ('41.20'). La
        // vista show recarga el reporte desde la BD, así que si firmáramos con los valores en
        // memoria el hash NO casaría al recargar → falso positivo de "alteración" en cada reporte.
        // Al refrescar, el hash se calcula sobre la representación de BD (la misma que verá show).
        $report->refresh();
        $report->signDocument(auth()->user(), $request);

        // AL ABRIR UN DÍA NUEVO se cierran los anteriores: cada DSR previo que ya pasó sus
        // 24 h queda sellado sobre su contenido definitivo. sealIfDue() es idempotente y
        // NO adelanta el cierre — un reporte que todavía está dentro de su ventana editable
        // no se toca, así que abrir el día de mañana no congela el de hoy antes de tiempo.
        // Se limita a los últimos días para no barrer la tabla entera en cada alta.
        foreach (DailyReport::where('id', '!=', $report->id)->latest('id')->limit(10)->get() as $previo) {
            $previo->sealIfDue();
        }

        return redirect()->route('daily_reports.show', $report->id)
                         ->with('success', 'Reporte creado exitosamente. Ahora puedes agregar logs de seguridad.');
    }

    /**
     * (2026-07-25) DÍA DE RODAJE — captura manual PRELLENADA (arranque en frío). Si el usuario
     * tecleó un número, se RESPETA (el formulario lo prellena con la derivación y el usuario lo
     * ajusta o lo confirma). Sin número, se DERIVA de la fecha del reporte (primer día con DSR = 1,
     * siguiente día distinto = 2…) y, sin ancla ni DSRs previos, cae a 1 (primer día por definición).
     *
     * @param  array $data  datos validados del encabezado (usa shoot_day y, si falta, report_date)
     * @return int
     */
    protected function resolveShootDay(array $data): int
    {
        if (isset($data['shoot_day']) && $data['shoot_day'] !== null && $data['shoot_day'] !== '') {
            return (int) $data['shoot_day'];
        }
        $derivado = \App\Support\ProductionCalendar::shootDayFor(
            isset($data['report_date']) ? $data['report_date'] : null
        );
        return $derivado !== null ? (int) $derivado : 1;
    }

    /**
     * 4. VISTA PREVIA DEL REPORTE (Estilo Magazine)
     */
    public function show($id)
    {
        // Eager-load del PDCA de cada log (dueño incluido) SOLO si la tabla existe en el esquema
        // (PROD aún no tiene el SQL). Sin la tabla, cargamos únicamente los logs → cero queries
        // a action_items y la tarjeta del log oculta el bloque PDCA (guard hasTable en la vista).
        // verifiedBy se carga junto al owner: el acta muestra QUIÉN cerró cada hallazgo,
        // y sin el eager-load sería una query por acción cerrada.
        $eager = Schema::hasTable('action_items')
            ? ['logs.actionItems.owner', 'logs.actionItems.verifiedBy']
            : ['logs'];
        // (2026-07-21) Normas N:M del hallazgo. Con guard de tabla: sin el delta de
        // standardables, HasStandards::standards() tronaría al resolver el pivote.
        // Sin este eager-load cada tarjeta de log dispararía su propia query (N+1).
        $ccHasStdPivot = Schema::hasTable('standardables');
        if ($ccHasStdPivot) {
            $eager[] = 'logs.standards';
        }
        $report = DailyReport::with($eager)->findOrFail($id);
        $standards = SafetyStandard::orderBy('category_name', 'asc')->get();

        // (2026-07-13) Catálogo único de eventos para el modal de log. DEFENSIVO: si la tabla
        // hazard_events no existe en PROD (SQL aún no aplicado), regresa colección vacía y el
        // picker degrada a su opción neutra sin romper la vista.
        $hazardEvents = Schema::hasTable('hazard_events')
            ? HazardEvent::active()->with('standards')->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get()
            : collect();

        $heatmap = [
            'electrical' => $report->logs->where('regulation_code', 'Bulletin #23')->count() > 0,
            'traffic' => $report->logs->where('regulation_code', 'Bulletin #21')->count() > 0,
            'heights' => $report->logs->where('regulation_code', 'Bulletin #6')->count() > 0,
            'fire' => $report->logs->where('regulation_code', 'Bulletin #16')->count() > 0,
        ];

        // LÓGICA DE COMPLIANCE: Bloquear después de 24 horas
        $isLocked = $report->isSealed();

        // AUTO-SELLADO DE CIERRE (2026-07-22): si el día ya cerró y el documento no quedó
        // sellado sobre su contenido final, se sella ahora como SISTEMA. Va aquí, de forma
        // perezosa, porque la app NO tiene cron de firmas: app/Console/Kernel.php tiene la
        // agenda VACÍA (2026-07-24). Es idempotente (si el sello vigente ya casa, no hace
        // nada), así que abrir el reporte mil veces no siembra mil firmas.
        $report->sealIfDue();

        // Schema::hasTable/hasColumn NO están cacheados en Laravel 8: cada llamada pega a
        // information_schema. Se resuelven UNA vez aquí —donde ya se decidió el eager-load—
        // y viajan a la vista, que antes los re-evaluaba dentro del @foreach de hallazgos.
        $ccHasActionItems = Schema::hasTable('action_items');
        $ccHasMitCol      = $ccHasActionItems && Schema::hasColumn('action_items', 'mitigation_image_path');

        return view('admin.dailyreports.show', compact(
            'report', 'standards', 'heatmap', 'isLocked', 'hazardEvents',
            'ccHasStdPivot', 'ccHasActionItems', 'ccHasMitCol'
        ));
    }

    /**
     * 5. GUARDAR UN "QUICK LOG" (Con Inteligencia Normativa)
     */
    public function storeLog(Request $request, $id)
    {
        $report = DailyReport::findOrFail($id);

        // Candado de cumplimiento PRIMERO (fail-fast, igual que update()): si el reporte está
        // sellado (>24 h) ni siquiera validamos ni procesamos el upload — se rechaza de una.
        if ($report->created_at->diffInHours(now()) >= 24) {
            return redirect()->back()->with('error', 'Por cumplimiento normativo, el reporte está sellado y no puede ser modificado después de 24 horas.');
        }

        $request->validate([
            'log_time' => 'required',
            'description' => 'required|string',
            'action_taken' => 'nullable|string',
            // (2026-07-13) Catálogo único de eventos: el Daily EXIGE el evento (como antes exigía
            // category_name). 'nullable'→'required|integer' sin 'exists:' para no romper PROD antes
            // del SQL de hazard_events. applyHazardEvent() (más abajo) resuelve la norma.
            'hazard_event_id' => 'required|integer',
            // 12 MB: las fotos de celular (capture="environment") superan fácil los 5 MB; el límite
            // viejo (5120) rechazaba la subida en silencio. PHP admite hasta 2G, así que 12 MB va sobrado.
            'photo' => 'nullable|image|max:12288'
        ]);

        // Manejo de imagen. (2026-07-21) Pasa por ImageCompressor: es la foto que más pesa
        // del DSR (promedio medido 3.5 MB, hasta 9.17 MB) y va incrustada en el PDF.
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $path = \App\Support\ImageCompressor::store($request->file('photo'), 'daily_reports/logs');
            $photoPath = $path !== null ? Storage::url($path) : null;
        }

        $logData = [
            'daily_report_id' => $report->id,
            'log_time' => $request->log_time,
            'description' => $request->description,
            'action_taken' => $request->action_taken,
            'photo_path' => $photoPath,
            // (2026-07-13) regulation_badge/regulation_code ya NO se resuelven aquí: el snapshot de
            // la norma lo copia applyHazardEvent() desde la norma PRINCIPAL del evento elegido.
            //'report_section' => 'general'
        ];

        // (2026-07-13) Dueño del hallazgo: autofirma con el usuario logueado. Guard por columna
        // (PROD aún no tiene el SQL). Con esto syncAutoActionItem toma el dueño del ActionItem vía
        // isset($this->created_by_id) → el PDCA nace con responsable en vez de huérfano.
        if (Schema::hasColumn('daily_logs', 'created_by_id')) {
            $logData['created_by_id'] = auth()->id();
        }

        $log = DailyLog::create($logData);

        // (2026-07-13) Catálogo único: sella el snapshot de la norma (badge/code de la norma
        // PRINCIPAL del evento) + guarda hazard_event_id. DEFENSIVO: no-op si el SQL no está.
        // DailyLog no usa HasStandards → solo snapshot + hazard_event_id (sin standardables).
        $evento = $log->applyHazardEvent($request->input('hazard_event_id'));

        // (2026-07-22) EPP VIVO. El hallazgo HEREDA el EPP de su evento del catálogo.
        //
        // Antes el EPP se declaraba al abrir el día y se congelaba ahí, pero el plan de
        // rodaje cambia: entra un plano nuevo, se agrega una escena, la locación resulta ser
        // otra cosa, y aparece EPP que nadie contempló a las 6 de la mañana. Al colgar el EPP
        // del EVENTO, el del día deja de ser una lista fija y pasa a ser la unión de lo
        // declarado al alta más lo que fueron exigiendo los hechos — se actualiza solo, sin
        // que nadie tenga que acordarse de editarlo.
        if ($evento !== null && Schema::hasColumn('daily_logs', 'required_ppe')) {
            $eppEvento = (Schema::hasColumn('hazard_events', 'required_ppe') && is_array($evento->required_ppe))
                ? array_values(array_filter($evento->required_ppe))
                : [];
            if (count($eppEvento)) {
                $log->required_ppe = $eppEvento;
                $log->save();
            }
        }

        // (2026-07-09) Motor PDCA: la "Acción Correctiva" del hallazgo genera una acción
        // correctiva rastreable (SLA por defecto 3 días; el Daily no maneja severidad por hallazgo).
        $log->syncAutoActionItem($request->input('action_taken'), 'action_taken');

        return redirect()->back()->with('success', 'Log agregado correctamente.');
    }

  /**
     * ACTUALIZAR REPORTE (Cierre de Día)
     */
    public function update(Request $request, $id)
    {
        $report = DailyReport::findOrFail($id);

        // Candado de seguridad en Backend
        if ($report->created_at->diffInHours(now()) >= 24) {
            return redirect()->back()->with('error', 'Por cumplimiento normativo, el reporte está sellado y no puede ser modificado después de 24 horas.');
        }

        $data = $request->validate([
            'executive_summary' => 'nullable|string',
            'hero_image' => 'nullable|image|max:12288', // 12 MB (foto de celular); ver nota en storeLog()
            // (2026-07-21) La foto del safety meeting también se puede subir en el cierre de
            // día: el DSR se crea al arrancar la jornada y la junta ocurre al call time, así
            // que muchas veces la foto llega después. Mismo mecanismo que el hero.
            'safety_meeting_photo' => 'nullable|image|max:12288',
        ]);

        // 1. Procesamos y subimos las imágenes (comprimidas; ver ImageCompressor).
        if ($request->hasFile('hero_image')) {
            $path = \App\Support\ImageCompressor::store($request->file('hero_image'), 'daily_reports/heroes');
            if ($path !== null) {
                $data['hero_image_path'] = Storage::url($path);
            }
        }
        if ($request->hasFile('safety_meeting_photo') && Schema::hasColumn('daily_reports', 'safety_meeting_photo_path')) {
            $path = \App\Support\ImageCompressor::store($request->file('safety_meeting_photo'), 'daily_reports/meetings');
            if ($path !== null) {
                $data['safety_meeting_photo_path'] = Storage::url($path);
            }
        }

        // 2. ELIMINAMOS los archivos del array para que SQL no intente guardarlos como columna
        unset($data['hero_image'], $data['safety_meeting_photo']);

        // 3. Ahora sí, guardamos limpiamente
        $report->update($data);

        // 4. RE-SELLADO (2026-07-21). executive_summary y hero_image_path SÍ son columnas del
        //    encabezado, así que entran en canonicalSignaturePayload(). Antes, el cierre de día
        //    los cambiaba y NUNCA se volvía a firmar → el sello del pie pasaba a "la firma no
        //    coincide" y el documento se auto-acusaba de alterado sin que nadie lo alterara.
        //    Mientras el reporte es editable (< 24 h) la firma debe seguir al contenido; la
        //    anterior NO se borra (digital_signatures es un histórico y verifyLatestSignature
        //    compara contra la de mayor id), así que la cadena de custodia queda completa.
        //    Sólo se re-firma si el contenido CAMBIÓ de verdad: reenviar el formulario sin
        //    tocar nada no debe sembrar firmas idénticas en el histórico. Y va blindado en
        //    try/catch (mismo criterio que AddendumController): que falle el sellado no
        //    puede costarle al usuario el cierre de día que ya se guardó.
        $report->refresh();
        try {
            $ultima = Schema::hasTable('digital_signatures') ? $report->signatures()->latest('id')->first() : null;
            if ($ultima === null || !hash_equals($ultima->document_hash, $report->computeDocumentHash())) {
                $report->signDocument(auth()->user(), $request);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo re-sellar el DSR #' . $report->id . ': ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reporte de cierre actualizado correctamente.');
    }
}