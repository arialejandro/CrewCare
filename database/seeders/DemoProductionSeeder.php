<?php

namespace Database\Seeders;

use App\Models\ActionItem;
use App\Models\cmedic;
use App\Models\DailyLog;
use App\Models\DailyReport;
use App\Models\HazardEvent;
use App\Models\hazardnotification;
use App\Models\InjuryReport;
use App\Models\Production;
use App\Models\ScoutingReport;
use App\Models\SfxEvent;
use App\Models\unsafecond;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\SoloEnLocal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DemoProductionSeeder — el CORPUS que ejercita la cadena completa (2026-07-24).
 *
 * ============================ PARA QUÉ EXISTE ============================
 * El reporte de wrap es un contraste PREDICHO-vs-REAL y hoy ese cruce no tiene datos: no por
 * falta de esquema, sino porque el tejido conectivo se construyó DESPUÉS que los datos. Medido
 * antes de este seeder: `hazard_event_id` 0 de 35 en daily_logs, `scouting_report_id` 0 de 8 en
 * los gemelos, `production_id` 0 de 8 en los DSR, y `sfx_events` con CERO filas — el módulo de
 * efectos especiales nunca se había usado.
 *
 * Este seeder crea una producción completa y COHERENTE para que el wrap tenga qué contrastar, y
 * de paso para poder demostrar la app: cada peligro evaluado en un scouting puede cruzarse
 * contra los eventos que ocurrieron en ESA locación, porque ambos hablan el mismo idioma (el
 * catálogo de 207 eventos) y comparten la misma llave.
 *
 * ============================ FORMA (decisión del owner) ============================
 * 2 semanas de prep + 16 días de rodaje, contando de lunes a sábado (el domingo no cuenta).
 * El calendario se ancla A HOY —el día 16 es el día en que se siembra— y el wrap queda 2 días
 * trabajados después: así el countdown tiene algo que contar y el tablero muestra una producción
 * VIVA, no una terminada. Y como se calcula al correr, la demo no caduca.
 *
 * ============================ QUÉ RESPETA ============================
 *  · **Los 5 DSR que ya tienen imágenes NO se recrean**: se conservan con su foto, su bitácora y
 *    su autor, y sólo se les mueve la FECHA para que encajen en el calendario. Los 3 que se re-
 *    fechan y estaban sellados se VUELVEN A SELLAR (`signDocumentAsSystem`): la firma anterior no
 *    se borra —digital_signatures es un histórico— así que la cadena de custodia queda completa y
 *    el documento NO aparece como "ALTERADO". Sin ese re-sellado, mover la fecha rompería el hash.
 *  · **Los usuarios son los REALES de la instancia**, emulando sus roles (médicos, HOD, crew). No
 *    se inventa gente: un corpus con nombres falsos no demuestra nada de la app.
 *  · **Las 3 filas de prueba vacías de daily_reports (sin foto, sin crew, sin locación) NO se
 *    tocan** y se quedan con `production_id` NULL, así que caen fuera del calendario de la demo.
 *
 * ============================ CÓMO SE BORRA ============================
 * Todo lo que este seeder CREA lleva el marcador self::MARCA en un campo visible. Volver a
 * correrlo purga lo marcado y lo rehace (es idempotente). Para borrar la demo a mano hay un SQL
 * en `database/owner-apply/2026-07-24-borrar-produccion-demo.sql`.
 *
 * Uso:  php artisan db:seed --class=DemoProductionSeeder
 */
class DemoProductionSeeder extends Seeder
{
    // CANDADO DE ENTORNO. Este corpus existe para probar el reporte de wrap y para demostrar la
    // app; en la base de un cliente sería basura indistinguible de lo real hasta que alguien la
    // busque. Fuera de `local` el seeder se niega a correr — ver el porqué en el trait.
    use SoloEnLocal;

    /** Marcador de datos de demostración. Debe verse en pantalla: nada debe confundirse con real. */
    const MARCA = '[DEMO]';

    /** Cuántos días de rodaje tiene el calendario. */
    const DIAS_RODAJE = 16;

    /** Semanas de prep (lunes a sábado, el domingo no cuenta). */
    const SEMANAS_PREP = 2;

    /** Días trabajados que faltan para el wrap al momento de sembrar (para que haya countdown). */
    const DIAS_AL_WRAP = 2;

    /**
     * El calendario se ANCLA A HOY, no a fechas escritas a mano: el último día de rodaje es el
     * día de hoy y todo lo demás se calcula hacia atrás. Así la demo nunca caduca — si el owner
     * la enseña dentro de tres meses sigue viéndose como una producción viva, con su prep en
     * negativo y un wrap a la vuelta de la esquina, en vez de un corpus fosilizado en julio.
     *
     * @var string 'Y-m-d' del día 1 de rodaje
     */
    private $inicio;

    /** @var string 'Y-m-d' del wrap preestablecido (ajustable: el 3-10 % de las producciones se extiende) */
    private $wrap;

    /**
     * Locaciones de la demo. El GPS es lo que ata los gemelos a su scouting (ScoutingLocator),
     * así que tiene que ser real y estable, no aleatorio.
     */
    const LOCACIONES = [
        'cantoral' => ['Casa Cantoral',        'Av. Cantoral 128, Coyoacán, CDMX',        19.3572000, -99.1780000],
        'atlampa'  => ['Bodega Atlampa',       'Calle Naranjo 45, Atlampa, CDMX',         19.4577000, -99.1633000],
        'salon'    => ['Salón Fiesta',         'Eje 3 Sur 210, Benito Juárez, CDMX',      19.4098000, -99.1500000],
        'foro'     => ['Foro 3 · San Ángel',   'Av. Revolución 1500, Álvaro Obregón, CDMX', 19.3450000, -99.1900000],
    ];

    /** @var array<int,string> día de rodaje (1-based) => fecha 'Y-m-d' */
    private $dias = [];

    /** @var \App\Models\Production */
    private $prod;

    /** @var int */
    private $autorId;

    public function run()
    {
        // Lo PRIMERO, antes de tocar la base: si no es local, esto revienta y no siembra nada.
        $this->exigirEntornoLocal('la producción de DEMOSTRACIÓN completa (DSR, scoutings, gemelos, accidentes, consultas médicas y SPFX)');

        $this->prod = \App\Support\CurrentProduction::get();
        if (! $this->prod) {
            $this->command->error('No hay producción. Corre antes el seeder que crea la producción.');
            return;
        }

        $this->autorId = optional(User::role('super-admin')->first())->id;

        $this->calendario();
        $this->purgar();

        $this->fecharProduccion();
        $this->reubicarDsrExistentes();
        $ids = $this->crearDsr();
        $this->crearScoutings();
        $this->crearBitacoras($ids);
        $this->crearGemelos();
        $this->crearAccidentes();
        $this->crearConsultas();
        $this->crearSpfx($ids);
        $this->crearAccionesPendientes();

        $this->command->info('Producción demo lista: ' . count($this->dias) . ' días de rodaje, prep de '
            . self::SEMANAS_PREP . ' semanas. Día 1 ' . $this->inicio . ', wrap ' . $this->wrap . '.');
    }

    // ===========================================================================================
    // Calendario
    // ===========================================================================================

    /**
     * Arma el calendario hacia ATRÁS desde hoy: el último día de rodaje es hoy (o el sábado
     * anterior si hoy cae en domingo, porque el domingo no cuenta). De ahí salen el día 1 y,
     * hacia adelante, el wrap.
     */
    private function calendario()
    {
        $fin = Carbon::now()->startOfDay();
        while ($fin->dayOfWeek === Carbon::SUNDAY) {
            $fin->subDay();
        }

        // Hacia atrás: día 16 = hoy, 15 = el hábil anterior, etc.
        $fechas = [];
        $c = $fin->copy();
        for ($i = self::DIAS_RODAJE; $i >= 1; $i--) {
            $fechas[$i] = $c->toDateString();
            do { $c->subDay(); } while ($c->dayOfWeek === Carbon::SUNDAY);
        }
        ksort($fechas);
        $this->dias  = $fechas;
        $this->inicio = $fechas[1];

        // El wrap queda unos días trabajados adelante: sin eso el countdown no tendría qué contar.
        $w = $fin->copy();
        for ($i = 0; $i < self::DIAS_AL_WRAP; $i++) {
            do { $w->addDay(); } while ($w->dayOfWeek === Carbon::SUNDAY);
        }
        $this->wrap = $w->toDateString();
    }

    /** Fecha de un día de rodaje. */
    private function dia($n)
    {
        return isset($this->dias[$n]) ? $this->dias[$n] : end($this->dias);
    }

    /** Fecha de un día de PREP (negativo): hacia atrás desde el día 1, saltando domingos. */
    private function prep($n)
    {
        $c = Carbon::parse($this->inicio);
        $faltan = abs($n);
        while ($faltan > 0) {
            $c->subDay();
            if ($c->dayOfWeek !== Carbon::SUNDAY) {
                $faltan--;
            }
        }
        return $c->toDateString();
    }

    // ===========================================================================================
    // Purga (idempotencia)
    // ===========================================================================================

    /**
     * Borra SÓLO lo que este seeder creó (lleva self::MARCA). Lo preexistente no se toca.
     * El orden importa: primero los hijos, para no dejar action_items ni firmas colgando.
     */
    private function purgar()
    {
        $m = '%' . self::MARCA . '%';

        // Gemelos, accidentes y scoutings: se identifican por el texto marcado.
        $este = [
            [hazardnotification::class, 'description_hazard_unsafe_act'],
            [unsafecond::class,         'description_unsafe_cond'],
            [InjuryReport::class,       'what_happened'],
            [ScoutingReport::class,     'location_name'],
        ];
        foreach ($este as $par) {
            list($clase, $campo) = $par;
            $filas = $clase::where($campo, 'like', $m)->get();
            foreach ($filas as $f) {
                $this->borrarHijos($clase, $f->getKey());
                $f->delete();
            }
        }

        // Consultas médicas.
        cmedic::where('observations', 'like', $m)->delete();

        // SPFX.
        if (Schema::hasTable('sfx_events')) {
            SfxEvent::where('effect_label', 'like', $m)->delete();
        }

        // DSR creados aquí (los 5 con foto NO llevan marca y por eso sobreviven).
        $dsr = DailyReport::where('location_name', 'like', $m)->get();
        foreach ($dsr as $d) {
            DailyLog::where('daily_report_id', $d->id)->delete();
            if (Schema::hasTable('sfx_events')) {
                SfxEvent::where('daily_report_id', $d->id)->delete();
            }
            $this->borrarHijos(DailyReport::class, $d->id);
            $d->delete();
        }

        // Bitácoras marcadas que cuelgan de los DSR CONSERVADOS (esos no se borran, sus logs sí).
        DailyLog::where('description', 'like', $m)->delete();

        // Acciones sueltas marcadas.
        ActionItem::where('description', 'like', $m)->delete();
    }

    /** Quita firmas y acciones de un documento que se va a borrar. */
    private function borrarHijos($clase, $id)
    {
        if (Schema::hasTable('digital_signatures')) {
            DB::table('digital_signatures')->where('documentable_type', $clase)->where('documentable_id', $id)->delete();
        }
        if (Schema::hasTable('action_items')) {
            DB::table('action_items')->where('actionable_type', $clase)->where('actionable_id', $id)->delete();
        }
        if (Schema::hasTable('standardables')) {
            DB::table('standardables')->where('standardable_type', $clase)->where('standardable_id', $id)->delete();
        }
    }

    // ===========================================================================================
    // Producción y DSR
    // ===========================================================================================

    /** Fija el ancla y el wrap. Es lo que hace que el contador de días exista. */
    private function fecharProduccion()
    {
        $this->prod->start_date = $this->inicio;
        $this->prod->end_date   = $this->wrap;
        $this->prod->save();
        \App\Support\ProductionCalendar::forget();
    }

    /**
     * Mueve los 5 DSR con foto a días de rodaje repartidos (1, 4, 7, 11 y 16) para que las
     * imágenes reales queden salpicadas a lo largo del calendario en vez de amontonadas al
     * principio. Se conserva TODO su contenido; sólo cambia la fecha y se ata a la producción.
     *
     * Los que estaban sellados se re-sellan: mover la fecha cambia el hash y sin re-sellar el
     * documento se auto-acusaría de alterado.
     */
    private function reubicarDsrExistentes()
    {
        $mapa = [1 => 1, 3 => 4, 4 => 7, 5 => 11, 2 => 16];

        foreach ($mapa as $dsrId => $diaRodaje) {
            $r = DailyReport::find($dsrId);
            if (! $r) {
                continue;
            }
            $estabaSellado = Schema::hasTable('digital_signatures')
                && DB::table('digital_signatures')
                    ->where('documentable_type', DailyReport::class)
                    ->where('documentable_id', $r->id)->exists();

            $r->report_date = $this->dia($diaRodaje);
            $r->shoot_day   = $diaRodaje;
            if (Schema::hasColumn('daily_reports', 'production_id')) {
                $r->production_id = $this->prod->id;
            }
            $r->save();

            if ($estabaSellado) {
                $r->refresh();
                $r->signDocumentAsSystem('reordenado al calendario de la producción demo');
            }
        }
    }

    /**
     * Crea los días de rodaje que faltan. Cada uno con locación, crew, clima y junta de
     * seguridad — y la junta cae en los TRES estados posibles a propósito: realizada, declarada
     * NO realizada, y sin declarar (null). Un corpus donde todo salió bien no prueba nada.
     *
     * @return array<int,int> día de rodaje => id del DSR
     */
    private function crearDsr()
    {
        $ocupados = [1, 4, 7, 11, 16]; // los que ya tienen los DSR con foto
        $ids = [];

        // día => [clave de locación, crew, junta (true/false/null), resumen]
        $plan = [
            2  => ['cantoral', 138, true,  'Interiores casa. Jornada sin novedad; se reforzó el orden de cables en pasillo.'],
            3  => ['cantoral', 141, true,  'Segunda unidad en patio. Calor alto: se adelantó la comida y se rotó al crew de exteriores.'],
            5  => ['atlampa',   96, true,  'Arranque en bodega. Se marcó el perímetro de la zona de rigging.'],
            6  => ['atlampa',   94, false, 'Media jornada por lluvia. La junta NO se realizó: el crew entró directo a cubrir equipo.'],
            8  => ['atlampa',   99, true,  'Escenas de acción ligera. Stunt coordinator presente toda la jornada.'],
            9  => ['foro',     112, true,  'Traslado a foro. Se revisó la distribución eléctrica temporal antes del call.'],
            10 => ['foro',     118, null,  'Jornada larga en foro. (Junta sin declarar en el reporte original.)'],
            12 => ['foro',     107, true,  'Efectos de atmósfera. Se ventiló entre tomas y se avisó a talento con asma.'],
            13 => ['salon',    145, true,  'Secuencia de multitud. Se habilitaron dos salidas de emergencia adicionales.'],
            14 => ['salon',    149, true,  'Continuación de multitud. Se sumó personal de seguridad en accesos.'],
            15 => ['salon',    143, false, 'Reprogramación de última hora; la junta no se realizó y se documentó así.'],
        ];

        foreach ($plan as $dia => $d) {
            if (in_array($dia, $ocupados, true)) {
                continue;
            }
            list($locKey, $crew, $junta, $resumen) = $d;
            $loc = self::LOCACIONES[$locKey];

            $datos = [
                'report_date'       => $this->dia($dia),
                'shoot_day'         => $dia,
                'location_name'     => self::MARCA . ' ' . $loc[0],
                'slug_setting'      => in_array($locKey, ['foro', 'salon'], true) ? 'INT.' : 'EXT.',
                'slug_time'         => 'DÍA',
                'call_time'         => $dia % 3 === 0 ? '05:30' : '07:00',
                'weather_condition' => $junta === false ? 'Lluvia' : 'Despejado',
                'weather_min_temp'  => 14,
                'weather_max_temp'  => $dia % 4 === 0 ? 31 : 26,
                'crew_count'        => $crew,
                'executive_summary' => $resumen,
                'author_name'       => 'Producción Demo',
                'nearest_hospital'  => 'Hospital General de México',
                'ambulance_company' => 'Ambulancias Vitales',
                'medic_name'        => 'Jose Luis',
                'safety_meeting_time' => '07:15',
            ];
            if (Schema::hasColumn('daily_reports', 'created_by_id')) {
                $datos['created_by_id'] = $this->autorId;
            }
            if (Schema::hasColumn('daily_reports', 'production_id')) {
                $datos['production_id'] = $this->prod->id;
            }
            if (Schema::hasColumn('daily_reports', 'safety_meeting_held')) {
                $datos['safety_meeting_held'] = $junta;
            }

            $r = DailyReport::create($datos);
            $r->refresh();
            $r->signDocumentAsSystem('corpus de demostración');
            $ids[$dia] = $r->id;
        }

        // Los conservados también se devuelven, para poder colgarles bitácora y SPFX.
        foreach ([1 => 1, 4 => 3, 7 => 4, 11 => 5, 16 => 2] as $dia => $dsrId) {
            if (DailyReport::where('id', $dsrId)->exists()) {
                $ids[$dia] = $dsrId;
            }
        }
        ksort($ids);

        return $ids;
    }

    // ===========================================================================================
    // Scoutings — el lado PREDICHO del contraste
    // ===========================================================================================

    /**
     * Un scouting por locación, todos en PREP (antes del día 1), cada uno con peligros que
     * llevan su `event_id` del catálogo.
     *
     * ⚠ Esa llave es lo único que hace posible el reporte de wrap: sin ella el scouting predice
     * con un vocabulario propio y los eventos ocurren con otro, y no hay forma de cruzarlos.
     * Se deja UN peligro a propósito sin evento para que el wrap tenga qué reportar como
     * "sin clasificar" — el hueco declarado es información, el hueco escondido es mentira.
     */
    private function crearScoutings()
    {
        $porCat = function ($cat, $n = 2) { return $this->eventos($cat, $n); };

        // ⚠ Las categorías son las REALES del catálogo (`hazard_events.category`), no inventadas.
        // La primera versión de este seeder usó 'slips' y 'rigging', que NO existen — el
        // resultado fue silencioso y por eso peligroso: los peligros se guardaron igual, pero
        // SIN event_id, o sea sin la llave que hace posible el wrap. eventos() aborta si una
        // categoría no devuelve nada, precisamente para que ese fallo no se repita callado.
        $plan = [
            'cantoral' => [-12, -10, 1,  ['electrical', 'access', 'heights']],
            'atlampa'  => [-9,  -7,  5,  ['rigging_hoist', 'structural', 'electrical']],
            'foro'     => [-6,  -4,  9,  ['electrical', 'fire', 'special']],
            'salon'    => [-3,  -1,  13, ['access', 'fire', 'weather']],
        ];

        foreach ($plan as $locKey => $p) {
            list($diaPrep, $diaVisita, $diaShoot, $cats) = $p;
            $loc = self::LOCACIONES[$locKey];

            $peligros = [];
            $stdIds   = [];
            foreach ($cats as $i => $cat) {
                foreach ($porCat($cat, 2) as $ev) {
                    $norma = $ev->standards()->first();
                    foreach ($ev->standards as $s) {
                        $stdIds[$s->id] = $s->id;
                    }
                    $prob = ['B', 'C', 'C', 'D'][count($peligros) % 4];
                    $cons = [4, 3, 3, 2][count($peligros) % 4];
                    $peligros[] = [
                        'key'          => $ev->category,
                        'hazard'       => $ev->name_es,
                        'likelihood'   => $prob,
                        'consequence'  => (string) $cons,
                        'rating'       => $this->rating($prob, $cons),
                        'control'      => 'Control declarado en prep; se verifica en el call del día.',
                        'residual'     => 'L',
                        'personnel'    => '1 safety',
                        'event_id'     => $ev->id,
                        'event_name'   => $ev->name_es,
                        'badge'        => $norma ? $norma->regulation_badge : null,
                        'code'         => $norma ? $norma->regulation_code : null,
                        'url'          => null,
                        'unclassified' => false,
                    ];
                }
            }

            // El hueco honesto: un peligro real que no está en el catálogo.
            $peligros[] = [
                'key' => null, 'hazard' => 'Perro guardián suelto en predio vecino',
                'likelihood' => 'C', 'consequence' => '2', 'rating' => $this->rating('C', 2),
                'control' => 'Acordar con el vecino el resguardo del animal durante la jornada.',
                'residual' => 'L', 'personnel' => null, 'event_id' => null, 'event_name' => null,
                'badge' => null, 'code' => null, 'url' => null,
                'unclassified' => true,
            ];

            $sc = ScoutingReport::create([
                'production_id'    => $this->prod->id,
                'production_name'  => $this->prod->name,
                'production_type'  => 'Largometraje',
                'location_name'    => self::MARCA . ' ' . $loc[0],
                'location_address' => $loc[1],
                'latitude'         => $loc[2],
                'longitude'        => $loc[3],
                'date_prep'        => $this->prep($diaPrep),
                'date_shoot'       => $this->dia($diaShoot),
                'date_wrap'        => $this->dia(min($diaShoot + 3, self::DIAS_RODAJE)),
                'loc_setting'      => in_array($locKey, ['foro', 'salon'], true) ? 'INT.' : 'EXT.',
                'complexity'       => 'media',
                'nearest_hospital' => 'Hospital General de México',
                'hospital_eta'     => '12 min',
                'assembly_point'   => 'Estacionamiento norte',
                'ambulance_company' => 'Ambulancias Vitales',
                'risk_assessment'  => $peligros,
                'exec_summary'     => 'Scouting de la locación durante prep. ' . count($peligros) . ' peligros evaluados.',
                'make_by'          => 'Producción Demo',
                'make_date'        => $this->prep($diaVisita),
                'created_by_id'    => $this->autorId,
                'status'           => 'final',
            ]);

            if (! empty($stdIds) && Schema::hasTable('standardables')) {
                $sc->standards()->sync(array_values($stdIds));
            }
            $sc->refresh();
            $sc->signDocumentAsSystem('corpus de demostración');
        }
    }

    /**
     * Eventos del catálogo por categoría. Los ids NO se escriben a mano: se resuelven en tiempo
     * de ejecución, así el seeder sigue sirviendo en una instancia cuyo catálogo tenga otros ids.
     *
     * ⚠ ABORTA si la categoría no existe. La primera versión devolvía una colección vacía y el
     * corpus se sembraba igual, sólo que con `hazard_event_id` NULL — es decir, reproduciendo
     * exactamente el problema que este seeder viene a resolver, y en silencio. Un seeder que
     * miente es peor que uno que truena.
     *
     * @return \Illuminate\Support\Collection
     */
    private function eventos($cat, $n = 2)
    {
        $ev = HazardEvent::where('category', $cat)->where('is_active', 1)->orderBy('id')->limit($n)->get();
        if ($ev->isEmpty()) {
            throw new \RuntimeException(
                "La categoría '{$cat}' no existe en hazard_events (o no tiene eventos activos). "
                . 'Corrige el plan del seeder: sin evento del catálogo no hay llave para el reporte de wrap.'
            );
        }
        return $ev;
    }

    /** Un solo evento de la categoría. @return \App\Models\HazardEvent */
    private function evento($cat)
    {
        return $this->eventos($cat, 1)->first();
    }

    /** Matriz 5×5 canónica (misma que ScoutingReportController::riskRating). */
    private function rating($l, $c)
    {
        $m = [
            'A' => ['M', 'H', 'E', 'E', 'E'],
            'B' => ['M', 'M', 'H', 'E', 'E'],
            'C' => ['L', 'M', 'H', 'H', 'E'],
            'D' => ['L', 'L', 'M', 'H', 'H'],
            'E' => ['L', 'L', 'M', 'M', 'H'],
        ];
        return isset($m[$l][$c - 1]) ? $m[$l][$c - 1] : null;
    }

    // ===========================================================================================
    // Bitácoras — el lado REAL del contraste
    // ===========================================================================================

    /**
     * Hallazgos del día con su `hazard_event_id`. Aquí es donde el cruce se vuelve posible:
     * el mismo catálogo que usó el scouting para PREDECIR es el que usa la bitácora para
     * REGISTRAR lo que pasó.
     */
    private function crearBitacoras($ids)
    {
        $plan = [
            1  => [['electrical', '08:40', 'Extensión sin tierra alimentando monitor de video.', 'Se sustituyó por cable con tierra.'],
                   ['access',     '14:10', 'Derrame de agua junto a mesa de catering.', 'Se secó y se puso señalización.']],
            3  => [['heights',    '11:05', 'Escalera apoyada sin amarre en fachada.', 'Se amarró y se asignó vigía.']],
            5  => [['rigging_hoist', '09:20', 'Eslinga con hilo cortado en punto de izaje.', 'Se retiró del servicio y se etiquetó.'],
                   ['structural', '16:45', 'Tarima con tablón flojo en zona de tránsito.', 'Se fijó el tablón.']],
            7  => [['electrical', '07:50', 'Tablero temporal sin tapa a la intemperie.', 'Se colocó tapa y se elevó del piso.']],
            9  => [['fire',       '13:30', 'Extintor de foro con manómetro en rojo.', 'Se reemplazó por uno vigente.']],
            11 => [['special',    '10:15', 'Máquina de humo operando sin ventilación cruzada.', 'Se abrieron portones entre tomas.'],
                   ['electrical', '18:00', 'Feeder cruzando paso peatonal sin rampa.', 'Se instaló rampa protectora.']],
            13 => [['access',     '08:05', 'Salida de emergencia bloqueada por utilería.', 'Se despejó y se marcó el área.']],
            15 => [['weather',    '12:40', 'Ráfagas de viento sobre estructura ligera.', 'Se arrió la lona y se ancló la estructura.']],
            16 => [['access',     '09:00', 'Acceso de ambulancia obstruido por vehículo de crew.', 'Se retiró el vehículo; se señalizó el carril.']],
        ];

        foreach ($plan as $dia => $filas) {
            if (! isset($ids[$dia])) {
                continue;
            }
            foreach ($filas as $f) {
                list($cat, $hora, $desc, $accion) = $f;
                $ev = $this->evento($cat);
                $norma = $ev->standards()->first();

                DailyLog::create([
                    'daily_report_id'  => $ids[$dia],
                    'log_time'         => $hora,
                    'description'      => self::MARCA . ' ' . $desc,
                    'action_taken'     => $accion,
                    'hazard_event_id'  => $ev ? $ev->id : null,
                    'regulation_badge' => $norma ? $norma->regulation_badge : 'NA',
                    'regulation_code'  => $norma ? $norma->regulation_code : null,
                    'created_by_id'    => $this->autorId,
                ]);
            }
        }
    }

    // ===========================================================================================
    // Gemelos — actos y condiciones atados a SU scouting
    // ===========================================================================================

    /**
     * Los gemelos se crean CON GPS de la locación, y el vínculo con el scouting se resuelve con
     * el mismo `ScoutingLocator` que usa el formulario real: no se escribe el id a mano. Así el
     * corpus prueba el localizador, no lo simula.
     */
    private function crearGemelos()
    {
        $crew = User::whereNotNull('email')->where('email', 'like', '%@%')
            ->whereHas('roles', function ($q) { $q->where('name', 'crew'); })
            ->orderBy('id')->limit(6)->pluck('id')->all();

        $actos = [
            ['cantoral', 2,  'electrical', 'Operador conectando extensión con las manos mojadas.', 'C', 3, 'prisa'],
            ['atlampa',  5,  'rigging_hoist', 'Técnico bajo carga suspendida durante el izaje.',   'B', 4, 'costumbre'],
            ['foro',     10, 'special',    'Personal sin protección respiratoria dentro de la niebla densa.', 'C', 2, 'desconocimiento'],
            ['salon',    14, 'access',     'Crew bloqueando la ruta de evacuación con cajas de equipo.', 'C', 3, 'prisa'],
        ];
        foreach ($actos as $i => $a) {
            list($locKey, $dia, $cat, $desc, $prob, $cons, $factor) = $a;
            $loc = self::LOCACIONES[$locKey];
            $ev  = $this->evento($cat);

            $datos = [
                'production_name'               => $this->prod->name,
                'name_loc'                      => self::MARCA . ' ' . $loc[0],
                'latitude'                      => $loc[2],
                'longitude'                     => $loc[3],
                'date_observed'                 => $this->dia($dia),
                'time_observed'                 => '10:30',
                'description_hazard_unsafe_act' => self::MARCA . ' ' . $desc,
                'action_taken'                  => 'Se detuvo la actividad y se corrigió en el momento.',
                'likelihood'                    => $prob,
                'consequence'                   => $cons,
                'risk_level'                    => $this->rating($prob, $cons),
                'action_status'                 => $i < 2 ? 'closed' : 'open',
                'make_by'                       => 'Producción Demo',
                'make_date'                     => $this->dia($dia),
                'created_by_id'                 => $this->autorId,
                'hazard_event_id'               => $ev ? $ev->id : null,
            ];
            foreach (['involved_user_id' => (isset($crew[$i]) ? $crew[$i] : null), 'human_factor' => $factor] as $col => $val) {
                if (Schema::hasColumn('hazardnotifications', $col)) {
                    $datos[$col] = $val;
                }
            }
            if (Schema::hasColumn('hazardnotifications', 'scouting_report_id')) {
                $datos['scouting_report_id'] = \App\Support\ScoutingLocator::nearestId($loc[2], $loc[3]);
            }

            $r = hazardnotification::create($datos);
            $r->refresh();
            $r->signDocumentAsSystem('corpus de demostración');
        }

        $condiciones = [
            ['cantoral', 3,  'access',     'Piso mojado permanente por fuga en lavabo de planta baja.', 'C', 2, false],
            ['atlampa',  6,  'structural', 'Barandal de mezzanine flojo en tramo de 4 m.',              'B', 4, true],
            ['foro',     12, 'electrical', 'Tablero de foro sin señalización ni tapa.',                 'C', 3, false],
            ['salon',    15, 'fire',       'Extintores del salón sin recarga vigente.',                 'C', 3, true],
        ];
        foreach ($condiciones as $i => $c) {
            list($locKey, $dia, $cat, $desc, $prob, $cons, $recurrente) = $c;
            $loc = self::LOCACIONES[$locKey];
            $ev  = $this->evento($cat);

            $datos = [
                'production_name'         => $this->prod->name,
                'name_loc'                => self::MARCA . ' ' . $loc[0],
                'latitude'                => $loc[2],
                'longitude'               => $loc[3],
                'date_observed'           => $this->dia($dia),
                'time_observed'           => '15:00',
                'description_unsafe_cond' => self::MARCA . ' ' . $desc,
                'corrective_action'       => 'Reparación a cargo del departamento responsable.',
                'likelihood'              => $prob,
                'consequence'             => $cons,
                'risk_level'              => $this->rating($prob, $cons),
                'action_status'           => $i === 0 ? 'closed' : 'open',
                'make_by'                 => 'Producción Demo',
                'make_date'               => $this->dia($dia),
                'created_by_id'           => $this->autorId,
                'hazard_event_id'         => $ev ? $ev->id : null,
            ];
            if (Schema::hasColumn('unsafeconds', 'is_recurrent')) {
                $datos['is_recurrent'] = $recurrente;
            }
            if (Schema::hasColumn('unsafeconds', 'scouting_report_id')) {
                $datos['scouting_report_id'] = \App\Support\ScoutingLocator::nearestId($loc[2], $loc[3]);
            }

            $r = unsafecond::create($datos);
            $r->refresh();
            $r->signDocumentAsSystem('corpus de demostración');

            // Acción correctiva con responsable: alimenta el tablero de pendientes.
            ActionItem::create([
                'actionable_type'  => unsafecond::class,
                'actionable_id'    => $r->id,
                'description'      => self::MARCA . ' ' . $datos['corrective_action'],
                'owner_id'         => isset($crew[$i]) ? $crew[$i] : null,
                'due_date'         => Carbon::parse($this->dia($dia))->addDays(5),
                'status'           => $i === 0 ? 'closed' : 'open',
                'closed_at'        => $i === 0 ? Carbon::parse($this->dia($dia))->addDays(2) : null,
                'source'           => 'seeder_demo',
                'source_field'     => 'corrective_action',
            ]);
        }
    }

    // ===========================================================================================
    // Accidentes
    // ===========================================================================================

    /**
     * Dos accidentes con perfiles OSHA distintos a propósito: uno REGISTRABLE con días de
     * ausencia y trabajo restringido, y otro de primeros auxilios que NO es registrable. Un
     * corpus con un solo tipo no deja ver que la app distingue.
     */
    private function crearAccidentes()
    {
        $lesionados = User::whereNotNull('email')->where('email', 'like', '%@%')
            ->orderBy('id', 'desc')->limit(2)->get();

        $casos = [
            [
                'dia' => 6, 'loc' => 'atlampa',
                'body_part' => 'Mano derecha', 'injury_type' => 'Laceración',
                'treatment_level' => 'medical_treatment', 'recordable' => true,
                'away' => 3, 'restricted' => 5,
                'what' => 'Al desmontar estructura, la mano quedó atrapada entre dos tramos de tubo.',
                'cause' => 'Falta de guantes y comunicación entre los dos técnicos que maniobraban.',
                'likelihood' => 'C', 'consequence' => 4,
            ],
            [
                'dia' => 12, 'loc' => 'foro',
                'body_part' => 'Ojo izquierdo', 'injury_type' => 'Irritación ocular',
                'treatment_level' => 'first_aid', 'recordable' => false,
                'away' => 0, 'restricted' => 0,
                'what' => 'Exposición prolongada a niebla de glicol sin ventilación cruzada.',
                'cause' => 'Ventilación insuficiente entre tomas de atmósfera.',
                'likelihood' => 'D', 'consequence' => 2,
            ],
        ];

        foreach ($casos as $i => $c) {
            $loc = self::LOCACIONES[$c['loc']];
            $u   = $lesionados->get($i);

            $r = InjuryReport::create([
                'production_title'     => $this->prod->name,
                'production_dates'     => $this->inicio . ' a ' . $this->wrap,
                'location'             => self::MARCA . ' ' . $loc[0],
                'incident_location'    => $loc[1],
                'latitude'             => $loc[2],
                'longitude'            => $loc[3],
                'incident_date'        => $this->dia($c['dia']),
                'reported_date'        => $this->dia($c['dia']),
                'time'                 => '15:20',
                'call_time'            => '07:00',
                'hours_worked_prior'   => 8.5,
                'user_id'              => $u ? $u->id : null,
                'name'                 => $u ? trim($u->name . ' ' . $u->lname) : 'Crew demo',
                'body_part'            => $c['body_part'],
                'injury_type'          => $c['injury_type'],
                'treatment_level'      => $c['treatment_level'],
                'is_recordable'        => $c['recordable'],
                'days_away_from_work'  => $c['away'],
                'days_restricted_work' => $c['restricted'],
                'what_happened'        => self::MARCA . ' ' . $c['what'],
                'what_caused'          => $c['cause'],
                'likelihood'           => $c['likelihood'],
                'consequence'          => $c['consequence'],
                'risk_level'           => $this->rating($c['likelihood'], $c['consequence']),
                'hospital'             => $c['recordable'] ? 'Hospital General de México' : null,
                'treatment_by'         => 'Jose Luis (médico de set)',
                'make_by'              => 'Producción Demo',
                'make_date'            => $this->dia($c['dia']),
                'created_by_id'        => $this->autorId,
            ]);
            $r->refresh();
            $r->signDocumentAsSystem('corpus de demostración');
        }
    }

    // ===========================================================================================
    // Consultas médicas
    // ===========================================================================================

    /**
     * Varias consultas repartidas, y DOS al mismo paciente en días cercanos: eso es lo que hace
     * aparecer el cintillo de tratamiento previo (el aviso anti-sobredosis) en la segunda.
     */
    private function crearConsultas()
    {
        $medico = User::role('medic')->orderBy('id')->first();
        $pacientes = User::whereNotNull('email')->where('email', 'like', '%@%')
            ->orderBy('id')->limit(5)->get();
        if (! $medico || $pacientes->isEmpty()) {
            return;
        }

        $manejos = array_keys(cmedic::MANAGEMENT_OPTIONS);

        $plan = [
            [0, 2,  'Cefalea tensional', 'Paracetamol 500 mg c/8 h', 'reposo'],
            [1, 4,  'Contractura cervical', 'Ibuprofeno 400 mg c/8 h', 'reposo'],
            [0, 5,  'Cefalea persistente — SEGUNDA consulta del mismo paciente', 'Paracetamol 500 mg', 'observacion'],
            [2, 8,  'Conjuntivitis irritativa por atmósfera', 'Lágrimas artificiales', 'valoracion'],
            [3, 11, 'Escoriación en antebrazo', 'Curación y antiséptico', 'curacion'],
            [4, 14, 'Deshidratación leve por calor', 'Suero oral', 'reposo'],
        ];

        foreach ($plan as $p) {
            list($idxPaciente, $dia, $dx, $med, $manejo) = $p;
            $paciente = $pacientes->get($idxPaciente);
            if (! $paciente) {
                continue;
            }

            $datos = [
                'id_user'           => $paciente->id,
                'created_by_id'     => $medico->id,
                'consultation_date' => $this->dia($dia),
                'diagnosis'         => $dx,
                'medication'        => $med,
                'observations'      => self::MARCA . ' Consulta del corpus de demostración.',
            ];
            if (Schema::hasColumn('cmedic', 'management') && in_array($manejo, $manejos, true)) {
                $datos['management'] = $manejo;
            }
            if (Schema::hasColumn('cmedic', 'medic_name')) {
                $datos['medic_name'] = trim($medico->name . ' ' . $medico->lname);
            }

            $c = cmedic::create($datos);
            $c->refresh();
            $c->signDocumentAsSystem('corpus de demostración');
        }
    }

    // ===========================================================================================
    // SPFX — el módulo que nunca se había usado
    // ===========================================================================================

    /**
     * `sfx_events` tenía CERO filas: el módulo existía y nadie lo había estrenado. Aquí se usa
     * de verdad, con efectos cerrados y uno todavía en curso.
     */
    private function crearSpfx($ids)
    {
        if (! Schema::hasTable('sfx_events')) {
            return;
        }
        $consumibles = DB::table('consumables')->orderBy('id')->limit(3)->pluck('id')->all();

        $plan = [
            [10, 'Haze de aceite mineral en foro',       'Ventilación cruzada entre tomas; aviso a talento con asma.', 'closed',  '09:10', '12:40'],
            [12, 'Niebla baja con hielo seco',           'Monitor de O2 en fosa; guantes criogénicos; nadie a nivel de piso.', 'closed', '10:00', '13:15'],
            [12, 'Llama abierta controlada (velas)',     'Zona despejada; extintor asignado por operador; ensayo en frío.', 'closed', '15:30', '17:00'],
            [16, 'Haze ligero en salón',                 'Densidad baja; portones abiertos; sin talento sensible en set.', 'active', '08:45', null],
        ];

        foreach ($plan as $i => $p) {
            list($dia, $label, $criterio, $estado, $ini, $fin) = $p;
            if (! isset($ids[$dia])) {
                continue;
            }
            SfxEvent::create([
                'consumable_id'   => isset($consumibles[$i % max(count($consumibles), 1)]) ? $consumibles[$i % count($consumibles)] : null,
                'daily_report_id' => $ids[$dia],
                'production_ref'  => $this->prod->name,
                'effect_label'    => self::MARCA . ' ' . $label,
                'safety_criteria' => $criterio,
                'status'          => $estado,
                'started_by_id'   => $this->autorId,
                'started_at'      => $this->dia($dia) . ' ' . $ini . ':00',
                'ended_at'        => $fin ? $this->dia($dia) . ' ' . $fin . ':00' : null,
            ]);
        }
    }

    // ===========================================================================================
    // Acciones en los TRES estados
    // ===========================================================================================

    /**
     * Abierta, cerrada y VENCIDA. La vencida es la que importa: es la única que el tablero tiene
     * que saber gritar, y sin un caso real en la base nadie se entera de si la alerta funciona.
     * (Vencida = status abierto con `due_date` en el pasado; no hay un estado 'overdue'.)
     */
    private function crearAccionesPendientes()
    {
        $cond = unsafecond::where('description_unsafe_cond', 'like', '%' . self::MARCA . '%')
            ->orderBy('id')->get();
        if ($cond->isEmpty()) {
            return;
        }

        $plan = [
            ['Reponer extintores con recarga vigente en todo el salón.', 'open',   +4,  null],
            ['Sustituir el barandal del mezzanine por uno estructural.', 'closed', -6,  -2],
            ['Señalizar y tapar el tablero eléctrico del foro.',          'open',   -3,  null], // VENCIDA
        ];

        foreach ($plan as $i => $p) {
            list($desc, $estado, $offsetDias, $cierreOffset) = $p;
            $c = $cond->get($i % $cond->count());
            $base = Carbon::now();

            ActionItem::create([
                'actionable_type' => unsafecond::class,
                'actionable_id'   => $c->id,
                'description'     => self::MARCA . ' ' . $desc,
                'due_date'        => $base->copy()->addDays($offsetDias),
                'status'          => $estado,
                'closed_at'       => $cierreOffset !== null ? $base->copy()->addDays($cierreOffset) : null,
                'source'          => 'seeder_demo',
                'source_field'    => 'corrective_action',
            ]);
        }
    }
}
