<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\ScoutingReport;
use App\Models\ScoutingDraft;
use App\Models\SafetyStandard;
use App\Models\HazardEvent;
use App\Models\Production;
use App\Http\Requests\StoreScoutingReportRequest;

/**
 * Controlador del nuevo módulo LEAN de Scouting / Location Risk Assessment.
 *
 * Construye un reporte combinado de 3 capas:
 *   (1) Encabezado de emergencia (quick-read).
 *   (2) Evaluación de riesgos H&S (13 categorías, corazón del reporte).
 *   (3) Capa operativa (resumen, viabilidad, acuerdos) almacenada como JSON.
 *
 * Reutiliza el patrón de subida de imágenes del locationController existente.
 * NO modifica el feature viejo de locación.
 *
 * 2026-07: se agregó EDICIÓN (edit/update). Para no duplicar ~150 líneas, el
 * ensamblado del reporte se extrajo a métodos privados (validateReport,
 * buildRiskAssessment, buildViabilityChecklist, buildAgreements,
 * assembleReportData) que usan tanto store() como update(). La AUTOFIRMA
 * (make_by / created_by_id / make_date) se fija SOLO en store(): al editar,
 * la firma original queda intacta y updated_at registra el cambio.
 */
class ScoutingReportController extends Controller
{
    /**
     * Categorías de la evaluación de riesgos H&S (clave => etiqueta).
     * Se centralizan aquí para usarlas tanto en create() como en store().
     *
     * (2026-07-18) Se agregaron las 25 claves FINAS del catálogo enriquecido —
     * las MISMAS que HazardEvent::categories() — para no divergir de la taxonomía
     * compartida. El bloque de 13 conserva su propia etiqueta de 'special'
     * (histórica de este formulario). Todas las claves ≤ 30 chars.
     *
     * @return array
     */
    private function categories()
    {
        return [
            // --- 13 categorías originales del Scouting ---
            'access'     => 'Accesos y egresos',
            'electrical' => 'Instalación eléctrica',
            'fire'       => 'Fuego / extintores',
            'heights'    => 'Alturas y caídas',
            'water'      => 'Agua / cuerpos de agua',
            'traffic'    => 'Tráfico vehicular / peatonal',
            'weather'    => 'Clima / exposición',
            'hazmat'     => 'Materiales peligrosos / calidad de aire',
            'structural' => 'Estructural / pisos / superficies',
            'confined'   => 'Espacios confinados',
            'biological' => 'Animales / plantas / biológico',
            'crowd'      => 'Seguridad pública / multitudes',
            'special'    => 'ACTIVIDADES ESPECIALES declaradas (armas/pirotecnia/stunts/aéreo/agua/off-road)',
            // --- 25 claves finas del catálogo enriquecido (espejo de HazardEvent::categories) ---
            'stunts_vehicular'  => 'Stunts vehiculares',
            'stunts_high_fall'  => 'Stunts de altura y caídas',
            'wire_work'         => 'Wire work / vuelo de performer',
            'fight_combat'      => 'Peleas, combate y armas blancas',
            'firearms'          => 'Armas de fuego y salvas',
            'fire_burn'         => 'Fuego sobre persona / quemas',
            'pyro_sfx'          => 'Pirotecnia y efectos especiales (SFX)',
            'railroad'          => 'Vías férreas / trenes en escena',
            'uncontrolled_env'  => 'Reality / documental en entorno no controlado',
            'water_work'        => 'Trabajo en agua (buceo / tanque / sumersión)',
            'aerial_work'       => 'Trabajo aéreo (helicóptero / avión / globo)',
            'drones_uas'        => 'Drones / UAS',
            'animals_wrangler'  => 'Animales en escena / wrangler',
            'electrical_water'  => 'Equipo eléctrico en/junto al agua',
            'camera_crane'      => 'Grúas y brazos de cámara (crane / jib / technocrane)',
            'camera_car'        => 'Camera car / process trailer / vehículos cámara',
            'stabilized_rig'    => 'Steadicam / gimbal / cuerpos estabilizados',
            'aerial_platform'   => 'Plataformas elevadoras (scissor / boom / condor)',
            'rigging_hoist'     => 'Rigging y tramoya (izaje / carga suspendida)',
            'portable_power'    => 'Baterías y energía portátil',
            'ev_hybrid'         => 'Vehículos eléctricos / híbridos enchufables',
            'utility_transport' => 'Transporte y vehículos utilitarios (off-road / no-cámara)',
            'crowd_action'      => 'Multitudes en escena / figuración de acción',
            'minors_physical'   => 'Menores en actividad física',
            'base_camp'         => 'Base camp / logística',
            // --- 4 categorías del catálogo del owner (CSV medidas_control, 2026-08-17) ---
            // Espejo de HazardEvent::categories() para no divergir de la taxonomía compartida.
            'health'            => 'Salud ocupacional / ergonomía',
            'security'          => 'Seguridad y protección (delitos / terceros)',
            'tools_machinery'   => 'Herramientas y maquinaria de taller',
            'safety_program'    => 'Programa de seguridad (gestión)',
        ];
    }

    /**
     * (2026-07-13) Propiedad de trabajo: unión de ids de normas (safety_standards)
     * de TODOS los eventos elegidos en la tabla de peligros. buildHazards() la llena;
     * store()/update() la usan para poblar el pivote `standardables` del scouting
     * (homologación: antes el scouting nunca poblaba standardables).
     *
     * @var array
     */
    private $hazardStandardIds = [];

    /**
     * (2026-07-13) Catálogo de "eventos posibles" (activos, con sus normas) para el
     * selector agrupado por contexto del formulario. Defensivo: colección vacía si la
     * tabla aún no existe (PROD sin el SQL) → el formulario sigue funcionando.
     *
     * @return \Illuminate\Support\Collection
     */
    private function hazardEventsCatalog()
    {
        if (!Schema::hasTable('hazard_events')) {
            return collect();
        }

        return HazardEvent::active()->with('standards')
            ->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get();
    }

    /**
     * Listado paginado de reportes de scouting (más recientes primero).
     */
    public function index()
    {
        // paginate(12): divisible entre las 1/2/3 columnas del grid de cards.
        // Aislamiento por propiedad (auditoría #1): autor, con bypass safety.consolidate.
        $reports = \App\Support\ReportVisibility::forCurrentUnit(ScoutingReport::query(), auth()->user())
            ->orderBy('id', 'desc')->paginate(12);
        return view('admin.scoutings.index', compact('reports'));
    }

    /**
     * API interna de geolocalización: scoutings registrados CERCA de unas coordenadas.
     *
     * La usan los formularios de seguridad (Cond./Acc. Inseguras, Accidentes) para
     * sugerir el NOMBRE de la locación donde está parado el usuario: "estás a 80 m
     * del scouting 'Panadería La Paz'" → se sugiere ese nombre, no la dirección.
     *
     * Desempate logístico por fechas: si hay 2+ scoutings dentro del radio (ej. dos
     * locaciones en la misma colonia), gana el que HOY cae dentro de su ventana
     * prep→wrap, o el de fecha de shoot más próxima a hoy; a igual fecha, el más cercano.
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'lat'    => 'required|numeric|between:-90,90',
            'lng'    => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:50|max:5000',
        ]);

        $lat    = (float) $request->input('lat');
        $lng    = (float) $request->input('lng');
        $radius = (int) $request->input('radius', 500);

        // Caja delimitadora en grados (~111,320 m por grado de latitud) para que el
        // filtro grueso lo haga MySQL y no barramos toda la tabla en PHP.
        $latDelta = $radius / 111320;
        $lngDelta = $radius / (111320 * max(cos(deg2rad($lat)), 0.01));

        $candidates = ScoutingReport::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude',  [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'location_name', 'location_address', 'latitude', 'longitude',
                   'date_prep', 'date_shoot', 'date_shoot_end', 'date_wrap']);

        $today   = now()->startOfDay();
        $matches = [];
        foreach ($candidates as $c) {
            $dist = $this->haversineMeters($lat, $lng, (float) $c->latitude, (float) $c->longitude);
            if ($dist > $radius) {
                continue; // dentro de la caja pero fuera del radio real
            }

            // Score de fechas: 0 = hoy cae en la ventana prep→wrap (o es el shoot);
            // si no, días de distancia a la ventana; null = scouting sin fechas.
            $dateScore = null;
            $start = $c->date_prep ?: $c->date_shoot;
            // El fin del rango de rodaje cuenta como cierre de la ventana cuando no hay wrap: una
            // locación "del 14 al 17" sigue siendo la locación de hoy el día 16. Sin esto, un rodaje
            // de varios días dejaba de sugerirse a partir del segundo.
            $end   = $c->date_wrap ?: ($c->date_shoot_end ?: $c->date_shoot);
            if ($start && $end) {
                $startDay = $start->copy()->startOfDay();
                $endDay   = $end->copy()->endOfDay();
                if ($today->between($startDay, $endDay)) {
                    $dateScore = 0;
                } else {
                    // Carbon 3: diffInDays es float con SIGNO → abs() para rankear por proximidad absoluta (como Carbon 2).
                    $dateScore = min(abs($today->diffInDays($startDay)), abs($today->diffInDays($endDay)));
                }
            }

            $matches[] = [
                'id'            => $c->id,
                'location_name' => $c->location_name,
                'address'       => $c->location_address,
                'distance_m'    => (int) round($dist),
                'date_shoot'    => $c->date_shoot ? $c->date_shoot->toDateString() : null,
                'date_score'    => $dateScore,
            ];
        }

        // Orden: primero por cercanía de fechas (sin fechas al final), luego por distancia.
        usort($matches, function ($a, $b) {
            $da = $a['date_score'] ?? PHP_INT_MAX;
            $db = $b['date_score'] ?? PHP_INT_MAX;
            if ($da !== $db) {
                return $da <=> $db;
            }
            return $a['distance_m'] <=> $b['distance_m'];
        });

        // Dedupe por nombre (varios scoutings del mismo lugar → queda el mejor rankeado).
        $seen = [];
        $unique = [];
        foreach ($matches as $m) {
            $key = mb_strtoupper(trim($m['location_name']));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $m;
        }

        return response()->json(['matches' => array_slice($unique, 0, 5)]);
    }

    /**
     * Distancia en metros entre dos puntos (fórmula de Haversine).
     */
    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2)
    {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Formulario de creación.
     * Pasa producciones, catálogo de normas y las 13 categorías a la vista.
     */
    public function create()
    {
        $productions  = Production::orderBy('name')->get();
        $standards    = SafetyStandard::orderBy('category_name')->get();
        $categories   = $this->categories();
        $hazardEvents = $this->hazardEventsCatalog();
        $prefill      = $this->defaultsFromLastScouting();

        // Borradores VIVOS de esta persona con fotos ya subidas. La vista los ofrece para retomar,
        // que es la diferencia entre "cerré la pestaña" y "perdí la jornada".
        $drafts = $this->draftsAvailable()
            ? ScoutingDraft::where('created_by_id', auth()->id())
                ->latest('updated_at')->limit(10)->get()
                ->map(fn ($d) => [
                    'key'    => $d->client_key,
                    'at'     => optional($d->updated_at)->toDateTimeString(),
                    'photos' => count($d->photoList()),
                ])->values()->all()
            : [];

        return view('admin.scoutings.create', compact('productions', 'standards', 'categories', 'hazardEvents', 'prefill', 'drafts'));
    }

    // =====================================================================================
    //  BORRADOR EN SERVIDOR (2026-09-15) — que cerrar la pestaña no cueste la jornada
    // =====================================================================================
    //
    // El borrador local (cc-drafts, IndexedDB) guarda el texto pero NO puede guardar las fotos:
    // un <input type=file> no es serializable, y así lo advierte su propia cabecera. El
    // 2026-09-14 eso costó un scouting con más de 20 fotos al cerrarse la vista por error.
    //
    // Aquí las fotos dejan de vivir en la pestaña: viajan EN CUANTO se capturan y el borrador
    // recuerda sus rutas. Al guardar el scouting no se re-suben — se referencian. Efecto lateral
    // bienvenido: el guardado final deja de tardar un minuto subiendo 30 MB de golpe.
    //
    // Las tres rutas son del AUTOR y sólo tocan SU borrador. Nada de esto se sella.

    /**
     * ¿Está disponible el borrador en servidor?
     *
     * 🪤 DEGRADE-SAFE a propósito, igual que el resto del esquema opcional de la app. Desplegar el
     * código ANTES de correr la migración es un orden perfectamente normal —Plesk copia los
     * archivos y la migración se corre después—, y en esa ventana `scouting_drafts` no existe. Sin
     * esta comprobación, la pantalla de captura devolvería 500: el módulo que existe para no perder
     * trabajo sería justo el que impide capturarlo. Sin tabla, todo se comporta como antes: las
     * fotos viajan al guardar, por el camino de siempre.
     */
    private ?bool $draftsOk = null;

    private function draftsAvailable(): bool
    {
        // Se memoiza POR PETICIÓN (propiedad de instancia), no por proceso. Con `static` el valor
        // sobreviviría en los workers de PHP-FPM ya calientes: tras correr la migración, los que
        // siguieran vivos mantendrían el borrador apagado hasta reciclarse, y el owner vería que
        // "no funciona" sin ninguna razón visible.
        if ($this->draftsOk === null) {
            try {
                $this->draftsOk = Schema::hasTable('scouting_drafts');
            } catch (\Throwable $e) {
                $this->draftsOk = false;
            }
        }

        return $this->draftsOk;
    }

    /** Autoguardado de los CAMPOS (sin fotos). Upsert por (autor, clave local). */
    public function draftSave(Request $request)
    {
        if (! $this->draftsAvailable()) {
            // Sin tabla, el navegador se queda con sus fotos y las manda al guardar: como antes.
            return response()->json(['ok' => false, 'reason' => 'unavailable'], 200);
        }

        $data = $request->validate([
            'client_key' => 'required|string|max:64',
            'values'     => 'nullable|array',
        ]);

        $draft = ScoutingDraft::updateOrCreate(
            ['created_by_id' => auth()->id(), 'client_key' => $data['client_key']],
            ['production_id' => \App\Support\CurrentProduction::id(), 'values' => $data['values'] ?? []]
        );

        return response()->json(['ok' => true, 'id' => $draft->id, 'photos' => $draft->photoList()]);
    }

    /**
     * Subida de fotos EN LOTE. Acepta varias por petición a propósito: una por petición
     * multiplica la latencia por el número de fotos, y en campo la red es justo lo escaso.
     *
     * Devuelve las rutas ya guardadas para que el formulario las pinte y las mande al guardar.
     * Tolerante a lo parcial: si una foto falla, las demás SÍ se guardan y se informa cuáles —
     * perder las nueve buenas porque la décima venía corrupta sería exactamente el bug que esto
     * viene a resolver.
     */
    public function draftPhotos(Request $request)
    {
        if (! $this->draftsAvailable()) {
            // 200 con ok:false, no 500: el navegador lo trata como "no se pudo poner a salvo",
            // conserva las fotos y las manda por el camino normal. Nada se pierde, nada se rompe.
            return response()->json(['ok' => false, 'reason' => 'unavailable', 'saved' => []], 200);
        }

        $data = $request->validate([
            'client_key' => 'required|string|max:64',
            'photos'     => 'required|array|min:1',
            'photos.*'   => 'required|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
        ]);

        $draft = ScoutingDraft::firstOrCreate(
            ['created_by_id' => auth()->id(), 'client_key' => $data['client_key']],
            ['production_id' => \App\Support\CurrentProduction::id(), 'values' => []]
        );

        $saved  = $draft->photos ?? [];
        $nuevas = [];
        $fallos = 0;

        // El resultado conserva la POSICIÓN de entrada (null donde falló). Sin eso, el navegador no
        // podría emparejar cada foto con su resultado y, si una fallara a media tanda, daría por
        // subidas las que no lo están — o al revés. Con null en su sitio, la que falló se queda en
        // el formulario y viaja por el camino normal.
        foreach ($request->file('photos') as $image) {
            if (! $image || ! $image->isValid()) {
                $nuevas[] = null;
                $fallos++;
                continue;
            }
            try {
                $entry    = ['path' => $this->storeUploadedImage($image, 'additional'), 'caption' => '', 'risk_map' => false];
                $saved[]  = $entry;
                $nuevas[] = $entry;
            } catch (\Throwable $e) {
                $nuevas[] = null;
                $fallos++;
                Log::warning('scouting draft: foto no guardada', ['e' => $e->getMessage()]);
            }
        }

        $draft->photos = $saved;
        $draft->save();

        return response()->json(['ok' => true, 'saved' => $nuevas, 'failed' => $fallos, 'total' => count($saved)]);
    }

    /** Descarta el borrador del autor (botón "descartar", o tras guardar el scouting). */
    public function draftDiscard(Request $request)
    {
        if (! $this->draftsAvailable()) {
            return response()->json(['ok' => true]);
        }

        $key   = (string) $request->input('client_key', '');
        $draft = ScoutingDraft::forAuthor($key, auth()->id());

        if ($draft) {
            // Las fotos NO se borran aquí: si el scouting se guardó, son suyas. Las que queden
            // realmente huérfanas las barre el aseo de archivos, no este botón.
            $draft->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * PRELLENADO de los datos que NO son de la locación sino de la PRODUCCIÓN.
     *
     * Tipo de producción, Gerente de Producción y Rep. de Seguridad son idénticos en todos los
     * scoutings de una misma producción, y hasta ahora había que teclearlos en cada uno. Se toman
     * del último scouting capturado: si algo cambia, se borra el campo y se escribe lo nuevo — el
     * siguiente ya hereda lo corregido.
     *
     * Sugerencia, no imposición: son campos normales y editables. La precedencia en la vista es
     * old() › el reporte que se edita › esto › vacío, así que NUNCA pisa lo que el usuario escribió
     * ni lo que ya tiene un reporte guardado. Sólo actúa al crear uno nuevo.
     *
     * Acotado a la producción en curso para que una instancia con varias no mezcle datos.
     */
    private function defaultsFromLastScouting(): array
    {
        $last = ScoutingReport::query()
            ->when(
                Schema::hasColumn('scouting_reports', 'production_id') && \App\Support\CurrentProduction::id(),
                fn ($q) => $q->where('production_id', \App\Support\CurrentProduction::id())
            )
            ->latest('id')
            ->first(['production_type', 'manager_name', 'safety_rep_name']);

        return [
            'production_type' => (string) ($last->production_type ?? ''),
            'manager_name'    => (string) ($last->manager_name ?? ''),
            'safety_rep_name' => (string) ($last->safety_rep_name ?? ''),
        ];
    }

    /**
     * Persistir un nuevo reporte de scouting.
     */
    public function store(StoreScoutingReportRequest $request)
    {
        // (2026-07-09) La validación —incluida la regla condicional SB-132— la resuelve
        // StoreScoutingReportRequest antes de entrar al método (withValidator/after hook).

        // Campos comunes (encabezado, riesgos, viabilidad, acuerdos, estatus).
        $reportData = $this->assembleReportData($request);

        // AUTOFIRMA (sistema cerrado): el autor y la fecha NO vienen del formulario — se
        // capturan del usuario autenticado y del servidor → no se pueden falsear. Esto es lo
        // que permite saber con certeza QUIÉN registró QUÉ y CUÁNDO (trazabilidad real).
        // Solo se fija aquí (creación); update() NUNCA toca estos campos.
        // NOMBRE DE CRÉDITOS, no `->name`. `name` es sólo el PRIMER nombre ("Ari"), y en un
        // documento con valor probatorio eso es AMBIGUO: con dos Genaros en la producción, la
        // instantánea deja de identificar a nadie. Además el propio documento ya mostraba el
        // crédito completo en el bloque de firma, así que la misma hoja traía dos nombres
        // distintos para la misma persona. displayName() cae al nombre corto si no hay crédito.
        // La trazabilidad dura sigue siendo `created_by_id`; esto es la etiqueta legible.
        $reportData['make_by']       = \App\Models\User::displayName(auth()->user());
        $reportData['created_by_id'] = auth()->id();
        $reportData['make_date']     = now()->toDateString();

        // (2026-09-07 · Unidades 2b) Unidad VIGENTE del contexto (null = principal → idéntico a hoy).
        if (\Illuminate\Support\Facades\Schema::hasColumn('scouting_reports', 'unit_id')) {
            $reportData['unit_id'] = \App\Support\CurrentUnit::id();
        }

        // ---- Imágenes (mismo patrón que locationController@store) ----
        if ($request->hasFile('main_image')) {
            $reportData['main_image_path'] = $this->storeUploadedImage($request->file('main_image'), 'main');
        }

        // ---- Fotos que YA viajaron (borrador en servidor) ----
        // Llegan como RUTAS, no como archivos: se subieron mientras se capturaba, así que aquí no
        // se re-suben. Por eso guardar un scouting de 20 fotos es instantáneo en vez de tardar un
        // minuto mandando 30 MB — y por eso cerrar la pestaña ya no cuesta el trabajo del día.
        //
        // 🔒 Cada ruta se comprueba contra el borrador DEL AUTOR (ScoutingDraft::owns). La ruta la
        // propone el navegador; sin esta verificación alguien podría mandar una cualquiera y colar
        // como evidencia un archivo que no subió — en un documento sellable eso no es aceptable.
        $items = [];
        $draftKey   = (string) $request->input('draft_key', '');
        $draftPaths = (array) $request->input('draft_photos', []);
        if ($draftKey !== '' && $draftPaths && $this->draftsAvailable()) {
            $draft = ScoutingDraft::forAuthor($draftKey, auth()->id());
            if ($draft) {
                $dCaptions = (array) $request->input('draft_photos_captions', []);
                $dRiskmap  = (array) $request->input('draft_photos_riskmap', []);
                foreach (array_values($draftPaths) as $i => $path) {
                    $path = (string) $path;
                    if (! $draft->owns($path)) {
                        continue; // ruta ajena o inventada: fuera, en silencio
                    }
                    $item = ['path' => $path, 'caption' => $this->cleanCaption($dCaptions[$i] ?? '')];
                    if (! empty($dRiskmap[$i])) {
                        $item['risk_map'] = true;
                    }
                    $items[] = $item;
                }
            }
        }

        if ($request->hasFile('additional_images')) {
            // Los pies de foto y el flag de mapeo llegan índice-alineados con los archivos
            // (mismo orden del DOM). additional_images_riskmap[] es "0"/"1" por imagen.
            $captions = $request->input('additional_images_captions', []);
            $riskmap  = $request->input('additional_images_riskmap', []);
            foreach ($request->file('additional_images') as $idx => $image) {
                if (!$image || !$image->isValid()) {
                    continue; // ignora slots vacíos/corruptos del arreglo
                }
                $item = [
                    'path'    => $this->storeUploadedImage($image, 'additional'),
                    'caption' => $this->cleanCaption($captions[$idx] ?? ''),
                ];
                if (!empty($riskmap[$idx])) {
                    $item['risk_map'] = true; // sólo se guarda cuando SÍ es mapeo de riesgos
                }
                $items[] = $item;
            }
        }

        // El cast 'array' serializa; se asigna como LISTA de {path, caption} (sin json_encode).
        //
        // 🪤 Esta asignación vivía DENTRO del `if (hasFile(...))`. Con el borrador en servidor eso
        // se vuelve un bug silencioso: el caso normal pasa a ser que las fotos YA viajaron y no
        // llegue ni un archivo en el envío final — y entonces el scouting se guardaba sin ninguna
        // imagen, sin error, después de que el usuario las capturó todas. Fuera del `if`, las dos
        // procedencias (subidas antes o adjuntas ahora) se guardan igual.
        if (! empty($items)) {
            $reportData['additional_images_paths'] = $items;
        }

        try {
            $report = ScoutingReport::create($reportData);
            // (2026-07-13) HOMOLOGACIÓN: poblar el pivote standardables con la unión de
            // normas de los eventos elegidos en la tabla de peligros (antes el scouting
            // NUNCA lo poblaba). buildHazards() (dentro de assembleReportData) ya llenó
            // $this->hazardStandardIds. Defensivo vía HasStandards::syncStandards.
            $report->syncStandards(array_values($this->hazardStandardIds));

            // (2026-07-22) SELLADO homologado a Injury/DSR. Decisión owner: un scouting NO
            // siempre nace borrador — muchas veces nace FINAL. La regla no es "obligar a pasar
            // por borrador", es: **"final" SIEMPRE está sellado, sin importar cuándo ocurra**.
            // Por eso store() también sella (antes solo update() lo hacía → un scouting que
            // nacía final quedaba SIN sello para siempre, como el #6). refresh() antes de firmar
            // es obligatorio: sin él el hash se calcula sobre los strings del request
            // (production_id "1") y al recargar con tipos de BD (int 1) el sello gritaría
            // "alterado" en falso. Mismo bug ya corregido en DailyReportController.
            if ($report->status === 'final') {
                $report->refresh();
                $report->signDocument(auth()->user(), $request);
            }

            // El borrador cumplió: el scouting ya existe. Se retira para que no reaparezca al
            // abrir otro nuevo. SOLO aquí — si el guardado falla más abajo, el borrador SIGUE VIVO
            // con sus fotos, que es justo la red que esto viene a tender.
            //
            // 🪤 BLINDADO A PROPÓSITO. Esto es limpieza contable, y una limpieza JAMÁS puede tumbar
            // un guardado que ya salió bien. Sin el try, bastaba con que la tabla no existiera
            // —código desplegado antes de correr la migración, que es un orden perfectamente
            // normal— para que el `catch` de abajo dijera "No se pudo guardar el reporte" DESPUÉS
            // de haberlo creado. El usuario lo daría por perdido y lo capturaría otra vez:
            // duplicado, por un borrador que no se pudo borrar. Si esto falla, el borrador se
            // queda huérfano y no pasa nada — reaparece una vez y se descarta.
            try {
                if ($draftKey !== '' && $this->draftsAvailable()) {
                    ScoutingDraft::forAuthor($draftKey, auth()->id())?->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('scouting: no se pudo retirar el borrador (el reporte SÍ se guardó)', [
                    'report' => $report->id, 'e' => $e->getMessage(),
                ]);
            }

            return redirect()->route('scoutings.show', $report->id)
                ->with('success', 'Reporte de scouting guardado correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al guardar el reporte de scouting: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'No se pudo guardar el reporte de scouting. Intenta de nuevo.');
        }
    }

    /**
     * Mostrar un reporte de scouting (vista homologada al Daily Safety Report).
     */
    public function show($id)
    {
        $report = ScoutingReport::findOrFail($id);

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga de un clic,
        // idéntica a window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = view('admin.scoutings.show', compact('report'))->render();
            return \App\Support\PdfExporter::download($html, 'SCOUT-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), [0, 0, 0, 0]);
        }

        return view('admin.scoutings.show', compact('report')
            + ['pdfUrl' => request()->fullUrlWithQuery(['pdf' => 1])]);
    }

    /**
     * Documento en formato oficial "Amazon MGM Studios – Risk Assessment Form".
     * Renderiza el MISMO reporte de scouting con la estructura exacta del PDF de
     * Amazon (encabezado, tabla de peligros, overview, matriz de riesgo, leyenda
     * Risk Rating → Required Action, jerarquía de control y definiciones).
     *
     * Bilingüe SIN tocar el locale global: ?lang=es|en fuerza el idioma solo de
     * este documento (los textos se resuelven con __('scouting.*', [], $lang)).
     * Es la parte "dejar listo el inglés" sin encender el selector de la app.
     */
    public function amazon($id, Request $request)
    {
        $report = ScoutingReport::findOrFail($id);
        $lang   = in_array($request->query('lang'), ['es', 'en'], true)
            ? $request->query('lang')
            : 'es';

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos (conserva ?lang) y la pasa por Browsershot (Chrome headless) → descarga
        // idéntica a window.print(). Formato CARTA (letter, márgenes 12mm); el botón vive en la
        // propia vista (chrome propio, no _report-v2-foot). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = view('admin.scoutings.amazon', compact('report', 'lang'))->render();
            return \App\Support\PdfExporter::download($html, 'SCOUT-' . $report->id . '-RA', [12, 12, 12, 12]);
        }

        return view('admin.scoutings.amazon', compact('report', 'lang'));
    }

    /**
     * Formulario de edición: mismo parcial _form que create(), pero precargado
     * con el reporte. Existe porque hay información (hospital, acuerdos, fotos)
     * que no se obtiene en la primera visita a la locación.
     */
    public function edit($id)
    {
        $report       = ScoutingReport::findOrFail($id);
        // Aislamiento por autor (auditoría #1): editar sólo el autor o la consolidación (leer es transversal).
        abort_unless(\App\Support\ReportVisibility::canMutate(auth()->user(), $report), 403,
            'Solo el autor o la consolidación de seguridad pueden editar este scouting.');
        $productions  = Production::orderBy('name')->get();
        $standards    = SafetyStandard::orderBy('category_name')->get();
        $categories   = $this->categories();
        $hazardEvents = $this->hazardEventsCatalog();

        return view('admin.scoutings.edit', compact('report', 'productions', 'standards', 'categories', 'hazardEvents'));
    }

    /**
     * Actualizar un reporte existente.
     *
     * Reglas clave:
     *  - La AUTOFIRMA original (make_by / created_by_id / make_date) NO se toca:
     *    sigue diciendo quién capturó el reporte por primera vez y cuándo.
     *    updated_at (automático de Eloquent) registra la última edición.
     *  - Imagen principal nueva REEMPLAZA a la anterior (y se borra el archivo viejo).
     *  - Imágenes adicionales nuevas se AGREGAN a las existentes (merge).
     */
    public function update(Request $request, $id)
    {
        $report = ScoutingReport::findOrFail($id);

        // Aislamiento por autor (auditoría #1): sólo el autor o la consolidación pueden editar/re-sellar.
        abort_unless(\App\Support\ReportVisibility::canMutate(auth()->user(), $report), 403,
            'Solo el autor o la consolidación de seguridad pueden editar este scouting.');

        $this->validateReport($request);

        // Mismo ensamblado que store(): riesgos, viabilidad, acuerdos, estatus.
        $reportData = $this->assembleReportData($request);

        // (2026-07-09) Bloqueo de estado PDCA: no se puede marcar "Final" mientras haya
        // acciones correctivas abiertas (ValidationException → se muestra en el show).
        if ($request->input('status') === 'final') {
            $report->assertActionItemsClosed('status');
        }

        // ---- Imagen principal: reemplazo (borra el archivo anterior) ----
        if ($request->hasFile('main_image')) {
            $this->deletePublicImage($report->main_image_path);
            $reportData['main_image_path'] = $this->storeUploadedImage($request->file('main_image'), 'main');
        }

        // ---- Imágenes adicionales: reconstrucción completa (permite editar pies de foto,
        //      quitar existentes y agregar nuevas). El formulario de edición envía siempre el
        //      oculto images_managed=1 y un existing_images[] por cada imagen conservada, así
        //      que el estado recibido es autoritativo (incluye "quitar todas"). ----
        if ($request->has('images_managed')) {
            // (1) Existentes conservadas — validadas contra las reales del reporte (anti-tamper).
            $originalPaths = array_map(function ($x) { return $x['path']; }, $report->additionalImagesList());
            $exPaths = (array) $request->input('existing_images', []);
            $exCaps  = (array) $request->input('existing_images_captions', []);
            $exRisk  = (array) $request->input('existing_images_riskmap', []);
            $kept = [];
            foreach ($exPaths as $i => $p) {
                if (!is_string($p) || $p === '' || !in_array($p, $originalPaths, true)) {
                    continue; // ignora rutas vacías o ajenas a este reporte
                }
                $keepItem = ['path' => $p, 'caption' => $this->cleanCaption($exCaps[$i] ?? '')];
                if (!empty($exRisk[$i])) {
                    $keepItem['risk_map'] = true;
                }
                $kept[] = $keepItem;
            }

            // (2) Borra del disco las existentes que YA NO se conservan.
            $keptPaths = array_map(function ($x) { return $x['path']; }, $kept);
            foreach ($originalPaths as $op) {
                if (!in_array($op, $keptPaths, true)) {
                    $this->deletePublicImage($op);
                }
            }

            // (3) Nuevas subidas, cada una con su pie de foto (índice-alineado con los archivos).
            $newItems = [];
            if ($request->hasFile('additional_images')) {
                $captions = $request->input('additional_images_captions', []);
                $riskmap  = $request->input('additional_images_riskmap', []);
                foreach ($request->file('additional_images') as $idx => $image) {
                    if (!$image || !$image->isValid()) {
                        continue;
                    }
                    $newItem = [
                        'path'    => $this->storeUploadedImage($image, 'additional'),
                        'caption' => $this->cleanCaption($captions[$idx] ?? ''),
                    ];
                    if (!empty($riskmap[$idx])) {
                        $newItem['risk_map'] = true;
                    }
                    $newItems[] = $newItem;
                }
            }

            $reportData['additional_images_paths'] = array_values(array_merge($kept, $newItems));
        }

        try {
            $report->update($reportData);

            // (2026-07-13) HOMOLOGACIÓN: re-sincroniza el pivote standardables con las
            // normas de los eventos elegidos (assembleReportData ya corrió buildHazards).
            $report->syncStandards(array_values($this->hazardStandardIds));

            // (2026-07-12 · endurecido 2026-07-22) MÓDULO 6 — no-repudio: un scouting "final"
            // SIEMPRE queda sellado sobre su contenido vigente. Dos correcciones homologadas al
            // DSR/Injury:
            //   (b) refresh() ANTES de firmar → el hash se calcula sobre los tipos reales de BD
            //       (int/decimal), no sobre los strings del request, para que verifyLatestSignature
            //       no marque "alterado" en falso al recargar.
            //   (c) hash_equals ANTES de re-firmar → NO se re-sella si el contenido no cambió (evita
            //       firmas duplicadas en cada guardado). Si SÍ cambió, se re-sella y esa firma nueva
            //       queda en el historial (digital_signatures) con su autor y fecha: el "final"
            //       siempre refleja el contenido actual, y cada re-sello queda registrado.
            // (2026-09-05 · Integridad) El re-sellado se guarda por "¿ya estaba SELLADO?", no solo por
            // "¿está en final?". Un scouting con FIRMA PREVIA DEBE re-sellarse al editarse, quede en el
            // estado que quede: antes, bajarlo de final dejaba su sello viejo sin que nadie se enterara
            // (un documento sellado es un documento que no cambió). Se CONSERVA la doctrina "un borrador
            // nunca sellado no se sella; 'final' siempre queda sellado" → por eso la condición también
            // entra cuando el estado nuevo es final aunque no hubiera firma (primer sellado al pasar a
            // final, igual que store()). hash_equals evita apilar firmas idénticas si nada cambió.
            $tieneFirmaPrevia = Schema::hasTable('digital_signatures') && $report->signatures()->exists();
            if ($report->status === 'final' || $tieneFirmaPrevia) {
                $report->refresh();
                $nuevoHash = $report->computeDocumentHash();
                $ultima    = Schema::hasTable('digital_signatures')
                    ? $report->signatures()->latest('id')->first()
                    : null;
                if ($ultima === null || !hash_equals((string) $ultima->document_hash, $nuevoHash)) {
                    $report->signDocument(auth()->user(), $request);
                }
            }

            return redirect()->route('scoutings.show', $report->id)
                ->with('success', 'Reporte de scouting actualizado correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar el reporte de scouting: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'No se pudo actualizar el reporte de scouting. Intenta de nuevo.');
        }
    }

    // =========================================================================
    //  Helpers privados compartidos por store() y update()
    // =========================================================================

    /**
     * Validación compartida. Solo location_name es obligatorio. Los arrays
     * paralelos (answer/risk/note/category_name) y las filas repetibles son
     * nullable. make_by/make_date NO se validan ni se aceptan del request:
     * son AUTOFIRMA (ver store()).
     */
    private function validateReport(Request $request)
    {
        // (2026-07-09) Reglas centralizadas en StoreScoutingReportRequest (reutilizadas
        // por store() vía inyección del FormRequest y por update() aquí).
        $request->validate(
            StoreScoutingReportRequest::baseRules(),
            StoreScoutingReportRequest::baseMessages()
        );

        // Regla condicional SB-132: si se declaran actividades especiales, exige el
        // desglose sb132_details (activity_type, scene_number, certified_personnel_required).
        $sb132 = StoreScoutingReportRequest::sb132Errors($request);
        if (!empty($sb132)) {
            throw \Illuminate\Validation\ValidationException::withMessages($sb132);
        }
    }

    /**
     * Matriz de riesgo Amazon MGM: Probabilidad (A–E) × Consecuencia (1–5) → clasificación.
     * Valores EXACTOS del formulario oficial. Devuelve 'L'|'M'|'H'|'E' o null.
     *
     * @param  string|null     $likelihood   A|B|C|D|E
     * @param  string|int|null $consequence  1..5
     * @return string|null
     */
    private function riskRating($likelihood, $consequence)
    {
        $rows = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
        $r = $rows[$likelihood] ?? null;
        $c = (int) $consequence;
        if ($r === null || $c < 1 || $c > 5) {
            return null;
        }
        // Filas A–E, columnas 1–5 (idénticas al PDF de Amazon MGM Studios).
        $matrix = [
            ['M', 'H', 'H', 'E', 'E'], // A (casi seguro)
            ['M', 'M', 'H', 'H', 'E'], // B (probable)
            ['L', 'M', 'M', 'H', 'E'], // C (moderado)
            ['L', 'M', 'M', 'H', 'H'], // D (improbable)
            ['L', 'L', 'M', 'M', 'H'], // E (raro)
        ];
        return $matrix[$r][$c - 1];
    }

    /**
     * Capa (2): ensambla risk_assessment como TABLA DE PELIGROS estilo Amazon MGM.
     * Una fila por peligro con Probabilidad, Consecuencia, Clasificación (calculada
     * con la matriz), Medidas de control, Riesgo residual y Personal requerido.
     * Conserva el snapshot de norma (badge/code/url) por fila y descarta filas
     * totalmente vacías (para que "agregar peligro" no ensucie el reporte).
     *
     * @return array
     */
    private function buildHazards(Request $request)
    {
        // (2026-07-13) HOMOLOGACIÓN: cada fila elige un EVENTO del catálogo único
        // (hz_event_id). El evento aporta la categoría (key) y su(s) norma(s); ya no
        // se usan hz_key ni hz_category_name.
        $this->hazardStandardIds = [];
        $hasEvents = Schema::hasTable('hazard_events');
        $hasUrl    = Schema::hasColumn('safety_standards', 'reference_url');

        // Arrays repetibles posteados (una entrada por fila de la tabla de peligros).
        $eventIds     = $request->input('hz_event_id', []);
        $hazards      = $request->input('hz_hazard', []);
        $likelihoods  = $request->input('hz_likelihood', []);
        $consequences = $request->input('hz_consequence', []);
        $controls     = $request->input('hz_control', []);
        $residuals    = $request->input('hz_residual', []);
        $personnel    = $request->input('hz_personnel', []);

        // Resolver los eventos elegidos (con sus normas) en una sola consulta.
        $eventsById = collect();
        if ($hasEvents) {
            $ids = array_filter(array_map('intval', (array) $eventIds));
            if (!empty($ids)) {
                $eventsById = HazardEvent::with('standards')->whereIn('id', $ids)->get()->keyBy('id');
            }
        }

        // El nº de filas lo marca el arreglo más largo (evento / texto / ejes).
        $rowCount = max(
            count((array) $eventIds), count((array) $hazards), count((array) $likelihoods),
            count((array) $consequences), count((array) $controls), count((array) $residuals), count((array) $personnel)
        );

        $out = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $eventId     = isset($eventIds[$i]) ? (int) $eventIds[$i] : 0;
            $hazardText  = trim((string) ($hazards[$i] ?? ''));
            $likelihood  = $likelihoods[$i]   ?? null;
            $consequence = $consequences[$i]  ?? null;
            $control     = trim((string) ($controls[$i]  ?? ''));
            $residual    = $residuals[$i]     ?? null;
            $person      = trim((string) ($personnel[$i] ?? ''));

            // Fila totalmente vacía → se descarta.
            if ($eventId === 0 && $hazardText === '' && empty($likelihood) && empty($consequence)
                && $control === '' && empty($residual) && $person === '') {
                continue;
            }

            $key       = null;
            $eventName = null;
            $badge     = null;
            $code      = null;
            $url       = null;

            $event = ($eventId && $eventsById->has($eventId)) ? $eventsById->get($eventId) : null;
            if ($event) {
                $key       = $event->category;   // homologa el eje de categoría
                $eventName = $event->name_es;
                $primary   = $event->standards->first();
                if ($primary) {
                    $badge = $primary->regulation_badge;
                    $code  = $primary->regulation_code;
                    if ($hasUrl) {
                        $url = $primary->reference_url;
                    }
                }
                // Acumula TODAS las normas del evento → pivote standardables del reporte.
                foreach ($event->standards as $std) {
                    $this->hazardStandardIds[$std->id] = $std->id;
                }
            }

            // Sin texto libre: usar el nombre del evento como etiqueta del peligro.
            if ($hazardText === '' && $eventName) {
                $hazardText = $eventName;
            }

            $out[] = [
                'key'         => $key,
                'hazard'      => $hazardText !== '' ? $hazardText : null,
                'likelihood'  => !empty($likelihood)  ? $likelihood            : null,
                'consequence' => !empty($consequence) ? (string) $consequence : null,
                'rating'      => $this->riskRating($likelihood, $consequence),
                'control'     => $control !== '' ? $control : null,
                'residual'    => !empty($residual) ? $residual : null,
                'personnel'   => $person !== '' ? $person : null,
                'event_id'    => $event ? $event->id : null,
                'event_name'  => $eventName,
                // (2026-07-24) ESCAPE HONESTO. El peligro DEBE llevar su evento del catálogo:
                // sin esa llave, el scouting predice con un vocabulario y los eventos ocurren
                // con otro, y el reporte de wrap no puede cruzar nada. Pero forzar el select
                // produciría clasificaciones falsas —alguien elige "lo más parecido" para poder
                // guardar— y una llave inventada es peor que ninguna. Así que se PERMITE guardar
                // sin evento y se MARCA: el wrap reporta "N peligros sin clasificar" y el hueco
                // queda a la vista en vez de disfrazado.
                'unclassified' => $event ? false : true,
                'badge'       => $badge,
                'code'        => $code,
                'url'         => $url,
            ];
        }

        return $out;
    }

    /**
     * Capa (3): viabilidad — filtra filas vacías del bloque repetible.
     *
     * @return array
     */
    private function buildViabilityChecklist(Request $request)
    {
        $viabAreas  = $request->input('viab_area', []);
        $viabStatus = $request->input('viab_status', []);
        $viabResp   = $request->input('viab_responsible', []);
        $viabNote   = $request->input('viab_note', []);

        $viabilityChecklist = [];
        foreach ($viabAreas as $i => $area) {
            $area = trim((string) $area);
            $resp = trim((string) ($viabResp[$i] ?? ''));
            $note = trim((string) ($viabNote[$i] ?? ''));
            if ($area === '' && $resp === '' && $note === '') {
                continue; // fila vacía
            }
            $viabilityChecklist[] = [
                'area'        => $area,
                'status'      => $viabStatus[$i] ?? null,
                'responsible' => $resp,
                'note'        => $note,
            ];
        }

        return $viabilityChecklist;
    }

    /**
     * Capa (3): acuerdos — filtra filas vacías del bloque repetible.
     *
     * @return array
     */
    private function buildAgreements(Request $request)
    {
        $agrItems  = $request->input('agr_item', []);
        $agrResp   = $request->input('agr_responsible', []);
        $agrDate   = $request->input('agr_date', []);
        $agrStatus = $request->input('agr_status', []);

        $agreements = [];
        foreach ($agrItems as $i => $item) {
            $item = trim((string) $item);
            $resp = trim((string) ($agrResp[$i] ?? ''));
            $date = trim((string) ($agrDate[$i] ?? ''));
            if ($item === '' && $resp === '' && $date === '') {
                continue; // fila vacía
            }
            $agreements[] = [
                'item'        => $item,
                'responsible' => $resp,
                'date'        => $date,
                'status'      => $agrStatus[$i] ?? null,
            ];
        }

        return $agreements;
    }

    /**
     * Ensambla el array de columnas COMUNES a crear y actualizar (todo menos
     * la autofirma y las imágenes, que se resuelven en store()/update()).
     *
     * @return array
     */
    private function assembleReportData(Request $request)
    {
        // Flag SB132: casilla "actividades especiales declaradas" → requiere RA específico.
        $requiresSpecificRa = $request->boolean('special_activities');
        // (2026-07-09) sb132_details se ensambla más abajo (sólo si la columna existe).

        // (2026-07-24) VÍNCULO CON LA PRODUCCIÓN. El select es opcional y por eso 1 de 2 scoutings
        // quedó sin `production_id`: la columna existía y nadie la llenaba, así que el reporte de
        // wrap no tenía por dónde acotar. Ahora, si no se eligió ninguna, se asume la vigente
        // (CurrentProduction) en vez de guardar NULL. Elegir otra a mano sigue mandando.
        $productionId = $request->input('production_id');
        if (empty($productionId)) {
            $productionId = \App\Support\CurrentProduction::id();
        }

        // Nombre de producción (texto) si se eligió una producción del catálogo.
        $productionName = $request->input('production_name');
        if (empty($productionName) && ! empty($productionId)) {
            $prod = Production::find($productionId);
            if ($prod) {
                $productionName = $prod->name;
            }
        }

        $data = [
            'production_id'       => $productionId,
            'production_name'     => $productionName,
            'production_type'     => $request->input('production_type'),
            'manager_name'        => $request->input('manager_name'),
            'safety_rep_name'     => $request->input('safety_rep_name'),
            'location_name'       => $request->input('location_name'),
            'location_address'    => $request->input('location_address'),
            'latitude'            => $request->input('latitude'),
            'longitude'           => $request->input('longitude'),
            'scene'               => $request->input('scene'),
            'date_prep'           => $request->input('date_prep'),
            'date_shoot'          => $request->input('date_shoot'),
            // Vacío → null, nunca '' (columna DATE en modo estricto no acepta cadena vacía).
            'date_shoot_end'      => $request->input('date_shoot_end') ?: null,
            'date_wrap'           => $request->input('date_wrap'),
            'loc_setting'         => $request->input('loc_setting'),
            'shoot_time'          => $request->input('shoot_time'),
            'complexity'          => $request->input('complexity'),

            'nearest_hospital'    => $request->input('nearest_hospital'),
            'hospital_address'    => $request->input('hospital_address'),
            'hospital_eta'        => $request->input('hospital_eta'),
            // Distancia al hospital (km) — la llena el buscador (ruta OSRM) o el emisor a mano.
            // null si viene vacía: DECIMAL no acepta '' en modo estricto.
            'hospital_distance_km'=> $request->filled('hospital_distance_km') ? $request->input('hospital_distance_km') : null,
            'emergency_access'    => $request->input('emergency_access'),
            'assembly_point'      => $request->input('assembly_point'),
            'ambulance_company'   => $request->input('ambulance_company'),
            'emergency_phone'     => $request->input('emergency_phone'),

            // Arrays directos: el cast 'array' los serializa una vez (NO json_encode).
            'risk_assessment'      => $this->buildHazards($request),
            'requires_specific_ra' => $requiresSpecificRa,

            'exec_summary'        => $request->input('exec_summary'),
            'viability_checklist' => $this->buildViabilityChecklist($request),
            'agreements'          => $this->buildAgreements($request),
            'operational_notes'   => $request->input('operational_notes'),

            'status'              => $request->input('status', 'draft'),
        ];

        // (2026-07-09) Desglose SB-132 — sólo si la columna existe (delta aplicado).
        // Se guarda el detalle cuando se declaran actividades especiales; si no, null.
        if (Schema::hasColumn('scouting_reports', 'sb132_details')) {
            $data['sb132_details'] = $requiresSpecificRa ? [
                'activity_type'                => $request->input('sb132_details.activity_type'),
                'scene_number'                 => $request->input('sb132_details.scene_number'),
                'certified_personnel_required' => $request->input('sb132_details.certified_personnel_required'),
            ] : null;
        }

        // (2026-07-12) MÓDULO 9 (inventarios) — aforo, equipo de emergencia y logística.
        // Guarda defensiva por columna: prod aún NO tiene el SQL de cimientos, así que
        // solo se asigna la clave si la columna existe (mismo patrón que sb132_details).
        if (Schema::hasColumn('scouting_reports', 'max_headcount')) {
            $mh = $request->input('max_headcount');
            $data['max_headcount'] = ($mh === null || $mh === '') ? null : (int) $mh;
        }
        if (Schema::hasColumn('scouting_reports', 'emergency_equipment_inventory')) {
            $data['emergency_equipment_inventory'] = $this->buildEmergencyEquipmentInventory($request);
        }
        if (Schema::hasColumn('scouting_reports', 'logistics_facilities')) {
            $data['logistics_facilities'] = $this->buildLogisticsFacilities($request);
        }

        // (2026-07-12) MÓDULO 8 (EPP) — captura del EPP requerido (no obligatorio en scouting).
        if (Schema::hasColumn('scouting_reports', 'required_ppe')) {
            $data['required_ppe'] = $this->buildRequiredPpe($request);
        }

        // (2026-08-08 · Parte D) Bandera "¿habrá ambulancia?" — tri-estado (null/1/0). Guarda
        // defensiva por columna (prod puede no tener el delta #54 aún). Vacío = sin declarar → null.
        if (Schema::hasColumn('scouting_reports', 'has_ambulance')) {
            $ha = $request->input('has_ambulance');
            $data['has_ambulance'] = ($ha === null || $ha === '') ? null : (bool) $ha;
        }

        return $data;
    }

    /**
     * MÓDULO 9: normaliza el inventario de equipo de emergencia a un arreglo estable
     * (extintores int|null, aed bool, botiquines int|null). Devuelve null si nada se
     * capturó, para no ensuciar el JSON ni la vista con una tarjeta vacía.
     *
     * @return array|null
     */
    private function buildEmergencyEquipmentInventory(Request $request)
    {
        $inv  = (array) $request->input('emergency_equipment_inventory', []);
        $fire = isset($inv['fire_extinguishers']) ? $inv['fire_extinguishers'] : null;
        $kits = isset($inv['first_aid_kits'])     ? $inv['first_aid_kits']     : null;

        $out = [
            'fire_extinguishers' => ($fire === null || $fire === '') ? null : (int) $fire,
            'aed'                => !empty($inv['aed']),
            'first_aid_kits'     => ($kits === null || $kits === '') ? null : (int) $kits,
        ];

        if ($out['fire_extinguishers'] === null && $out['first_aid_kits'] === null && !$out['aed']) {
            return null;
        }
        return $out;
    }

    /**
     * MÓDULO 9: normaliza instalaciones y logística (sanitarios bool, estaciones de
     * hidratación int|null, áreas de sombra bool). Devuelve null si nada se capturó.
     *
     * @return array|null
     */
    private function buildLogisticsFacilities(Request $request)
    {
        $log = (array) $request->input('logistics_facilities', []);
        $hyd = isset($log['hydration_stations']) ? $log['hydration_stations'] : null;

        $out = [
            'restrooms'          => !empty($log['restrooms']),
            'hydration_stations' => ($hyd === null || $hyd === '') ? null : (int) $hyd,
            'shade_areas'        => !empty($log['shade_areas']),
        ];

        if (!$out['restrooms'] && $out['hydration_stations'] === null && !$out['shade_areas']) {
            return null;
        }
        return $out;
    }

    /**
     * MÓDULO 8: normaliza el EPP requerido a una lista de strings únicos (máx 100 c/u).
     * Devuelve null si no se marcó nada.
     *
     * @return array|null
     */
    private function buildRequiredPpe(Request $request)
    {
        $ppe = $request->input('required_ppe', []);
        if (!is_array($ppe)) {
            return null;
        }

        $out = [];
        foreach ($ppe as $item) {
            $item = trim((string) $item);
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = mb_substr($item, 0, 100);
            }
        }

        return empty($out) ? null : array_values($out);
    }

    /**
     * Normaliza un pie de foto: string recortado a 300 caracteres (o '' si no aplica).
     * El escape para HTML lo hace Blade al renderizar ({{ }}).
     *
     * @param  mixed $value
     * @return string
     */
    private function cleanCaption($value): string
    {
        $s = is_string($value) ? trim($value) : '';
        return mb_substr($s, 0, 300);
    }

    /**
     * Guarda una imagen subida en el disco 'public' (scouting_images/) y
     * devuelve su URL pública (/storage/...). Mismo patrón de nombre que el
     * locationController original.
     *
     * @param  \Illuminate\Http\UploadedFile $image
     * @param  string $tag  'main' | 'additional'
     * @return string URL pública
     */
    private function storeUploadedImage($image, $tag)
    {
        // HEIC (iPhone) → JPEG si el servidor puede convertir; si no, la validación ya lo rechazó.
        $image    = \App\Support\ImageCompressor::normalizeForUpload($image);
        $filename = time() . '_' . $tag . '_' . uniqid() . '.' . \App\Support\ImageCompressor::safeExtensionOrBin($image);
        $path     = $image->storeAs('scouting_images', $filename, 'public');
        return Storage::url($path);
    }

    /**
     * Borra del disco 'public' un archivo guardado como URL /storage/...
     * (inverso de Storage::url). Silencioso si la ruta no existe.
     *
     * @param  string|null $url
     * @return void
     */
    private function deletePublicImage($url)
    {
        if (empty($url) || strpos($url, '/storage/') !== 0) {
            return;
        }
        $relative = substr($url, strlen('/storage/'));
        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
