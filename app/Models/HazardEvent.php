<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * HazardEvent — Catálogo ÚNICO de "eventos posibles" de seguridad (2026-07-13).
 *
 * Un evento realista (p. ej. "Trabajo en altura montando parrilla de foro")
 * etiquetado por:
 *   - context  : dónde ocurre (locación / set de filmación / construcción /
 *                adaptación de foros y sets / transversal).
 *   - category : qué tipo de peligro es (misma taxonomía cerrada que el Scouting:
 *                access, electrical, fire, heights, ...). Homologa los 5 reportes.
 *   - standards(): sus NORMAS equivalentes N:N (CSATF + STPS + OSHA a la vez) del
 *                  catálogo normativo `safety_standards`.
 *
 * Los 5 reportes comparten este catálogo: al elegir un evento se auto-etiqueta su
 * norma principal (snapshot badge/code en la fila) y se adjuntan TODAS sus normas
 * al pivote polimórfico `standardables` ya existente (vía HasStandards en cada
 * reporte). Ver database/owner-apply/2026-07-13-hazard-events-catalog.sql.
 */
class HazardEvent extends Model
{
    protected $table = 'hazard_events';

    protected $fillable = [
        'code',
        'context',
        'category',
        // (2026-08-04 · delta #51) Pictograma (_rm-icon) CURADO que sobreescribe el
        // icono derivado (categoría + palabra clave). NULL = usa el derivado. Cosmético.
        'risk_icon',
        'name_es',
        'name_en',
        'description_es',
        'description_en',
        // (2026-08-01 · delta #49) Medida de control PRE-PROPUESTA (editable en captura).
        // NULL = sin medida → el campo del formulario llega VACÍO (nunca texto inventado).
        // Llenarlas es trabajo de contenido del owner, no de código.
        'control_measure_es',
        'control_measure_en',
        'default_likelihood',
        'default_consequence',
        'sort_order',
        'is_active',
        // Verificación (2026-07-18, espejo del Paso 1c de las fichas SDS): solo
        // servidor, NUNCA por POST — ninguna regla de captura las incluye; solo
        // las escribe el flujo de verificación / el sellado inicial del SQL.
        'verified_at',
        'verified_by_id',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
        'default_consequence' => 'integer',
        'verified_at'         => 'datetime',
        // (2026-07-22) EPP mínimo de la familia de riesgo del evento. Lo hereda el hallazgo
        // del DSR al capturarlo. Poblado por HazardEventPpeSeeder (mapeo por categoría).
        'required_ppe'        => 'array',
    ];

    /**
     * Contextos canónicos (la agrupación que pidió el owner). El orden de este
     * arreglo es el orden en que se muestran los <optgroup> del selector.
     */
    public static function contexts()
    {
        return [
            'location'     => 'Locaciones',
            'film_set'     => 'Set de filmación',
            'construction' => 'Construcción',
            'set_build'    => 'Adaptación de foros y sets',
            'transversal'  => 'Transversal',
        ];
    }

    public static function contextsEn()
    {
        return [
            'location'     => 'Locations',
            'film_set'     => 'Film set',
            'construction' => 'Construction',
            'set_build'    => 'Soundstage & set build',
            'transversal'  => 'Cross-cutting',
        ];
    }

    /**
     * Medida de control PRE-PROPUESTA, localizada con respaldo a ES (delta #49).
     * Devuelve '' cuando el evento NO tiene medida redactada: el campo del
     * formulario llega vacío, NUNCA con texto inventado. PHP 7.4: sin nullsafe.
     */
    public function controlMeasure($lang = 'es')
    {
        $es = trim((string) ($this->control_measure_es ?? ''));
        $en = trim((string) ($this->control_measure_en ?? ''));
        if ($lang === 'en') {
            return $en !== '' ? $en : $es;
        }
        return $es !== '' ? $es : $en;
    }

    /**
     * Taxonomía de CATEGORÍA — comparte las 13 claves originales con el Scouting
     * (ScoutingReportController@categories) para homologar los reportes.
     *
     * (2026-07-18) Se agregaron las 25 claves FINAS del catálogo enriquecido
     * (EnrichedCatalogSeeder / data/enriched_catalog.php) — actividades de alto
     * riesgo antes agrupadas bajo 'special'. Decisión del owner: "25 finas
     * completas". La label de cada clave = el category_label crudo del data file.
     * Todas las claves ≤ 30 chars (columna hazard_events.category varchar(30)).
     * Estas MISMAS 25 claves están replicadas en ScoutingReportController@categories
     * para no divergir (su bloque de 13 conserva su propia label de 'special').
     */
    public static function categories()
    {
        return [
            // --- 13 claves originales (compartidas con el Scouting) ---
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
            'special'    => 'Actividades especiales (armas/pirotecnia/stunts/aéreo/agua/off-road)',
            // --- 25 claves finas del catálogo enriquecido (2026-07-18) ---
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
            // Nacen del archivo del owner: los eventos que agregó (fatiga, ergonomía,
            // ruido, catering, alergias = salud; robo/agresión = protección) y 8 eventos
            // existentes de talleres (sierras/clavadora) y de PROGRAMA (sin EPP, sin
            // inducción, subregistro) que él etiquetó. 'tools_machinery' y 'safety_program'
            // los aprobó explícitamente; 'health' y 'security' salieron del mismo archivo.
            'health'            => 'Salud ocupacional / ergonomía',
            'security'          => 'Seguridad y protección (delitos / terceros)',
            'tools_machinery'   => 'Herramientas y maquinaria de taller',
            'safety_program'    => 'Programa de seguridad (gestión)',
        ];
    }

    /**
     * Etiquetas de categoría en INGLÉS, en el MISMO orden que categories().
     *
     * (2026-07-22) El modelo ya era bilingüe en `context` (contexts/contextsEn) pero de
     * categoría sólo existía la versión ES, y desde este paso las categorías salen a la
     * cara del usuario como temas del safety meeting: sin esto, un DSR en inglés mostraría
     * los temas en español. El orden es idéntico al de categories() a propósito, para que
     * cualquier iteración (casillas, chips, optgroups) sea posicionalmente igual en ambos.
     *
     * NO es traducción literal sino terminología de set de EEUU: 'aerial_platform' son
     * "aerial lifts" (no "elevating platforms"), 'fire_burn' es "burn gag", 'crowd_action'
     * usa "background" (que es como se llama allá a la figuración), y 'utility_transport'
     * dice "non-picture" para distinguirlo del picture car y del camera car.
     */
    public static function categoriesEn()
    {
        return [
            // --- 13 claves originales (compartidas con el Scouting) ---
            'access'     => 'Access & egress',
            'electrical' => 'Electrical systems',
            'fire'       => 'Fire / extinguishers',
            'heights'    => 'Working at heights & falls',
            'water'      => 'Water / bodies of water',
            'traffic'    => 'Vehicle & pedestrian traffic',
            'weather'    => 'Weather / exposure',
            'hazmat'     => 'Hazardous materials / air quality',
            'structural' => 'Structural / floors & surfaces',
            'confined'   => 'Confined spaces',
            'biological' => 'Animals / plants / biological',
            'crowd'      => 'Public safety / crowds',
            'special'    => 'Special activities (weapons, pyro, stunts, aerial, water, off-road)',
            // --- 25 claves finas del catálogo enriquecido ---
            'stunts_vehicular'  => 'Vehicle stunts',
            'stunts_high_fall'  => 'High falls & fall stunts',
            'wire_work'         => 'Wire work / performer flying',
            'fight_combat'      => 'Fight / combat & edged weapons',
            'firearms'          => 'Firearms & blanks',
            'fire_burn'         => 'Burn gags / fire on performer',
            'pyro_sfx'          => 'Pyrotechnics / SFX',
            'railroad'          => 'Railroads / picture trains',
            'uncontrolled_env'  => 'Reality / doc - uncontrolled setting',
            'water_work'        => 'Water work (diving / tank / submersion)',
            'aerial_work'       => 'Aerial work (helicopter / aircraft)',
            'drones_uas'        => 'Drones / UAS',
            'animals_wrangler'  => 'Animals on set / wrangler',
            'electrical_water'  => 'Electrical in or near water',
            'camera_crane'      => 'Camera cranes / jib / technocrane',
            'camera_car'        => 'Camera car / process trailer',
            'stabilized_rig'    => 'Steadicam / gimbal rigs',
            'aerial_platform'   => 'Aerial lifts (scissor / boom / condor)',
            'rigging_hoist'     => 'Rigging & hoisting (suspended loads)',
            'portable_power'    => 'Batteries & portable power',
            'ev_hybrid'         => 'EV / plug-in hybrid vehicles',
            'utility_transport' => 'Transport & utility vehicles (non-picture)',
            'crowd_action'      => 'Crowd action / action background',
            'minors_physical'   => 'Minors - physical activity',
            'base_camp'         => 'Base camp / logistics',
            // --- 4 categorías del catálogo del owner (CSV, 2026-08-17), MISMO orden que categories() ---
            'health'            => 'Occupational health / ergonomics',
            'security'          => 'Security & protection (crime / third parties)',
            'tools_machinery'   => 'Shop tools & machinery',
            'safety_program'    => 'Safety program (management)',
        ];
    }

    /**
     * Etiquetas de categoría en el idioma activo. Es lo que deben consumir las vistas.
     *
     * @return array clave => etiqueta
     */
    public static function categoriesLocalized()
    {
        return app()->getLocale() === 'en' ? self::categoriesEn() : self::categories();
    }

    /**
     * Normas equivalentes (N:N). Un evento puede mapear a varias normas de
     * distintos marcos (CSATF + STPS + OSHA).
     */
    public function standards()
    {
        return $this->belongsToMany(
            SafetyStandard::class,
            'hazard_event_standard',
            'hazard_event_id',
            'safety_standard_id'
        );
    }

    /** Solo eventos activos, ordenados por contexto → sort_order → nombre. */
    public function scopeActive($query)
    {
        // Degrade-safe (espejo de SafetyStandard::scopeActive): sin la columna
        // is_active, no filtres — todo se trata como vigente. En prod la tabla
        // siempre nace con is_active en el mismo CREATE, así que este guard es
        // defensivo, pero mantiene coherente toda la maquinaria supportsActiveFlag().
        if (!self::supportsActiveFlag()) {
            return $query;
        }
        return $query->where('is_active', 1);
    }

    /** Memo del soporte de vigencia (is_active) en BD (ver supportsActiveFlag()). */
    protected static $activeFlagSupported = null;

    /**
     * ¿La BD tiene la columna de vigencia is_active? En hazard_events la columna nace
     * con el seed original (tinyint NOT NULL DEFAULT 1), así que aquí casi siempre es
     * true; el memo/guard existe por espejo de SafetyStandard y para no reventar en una
     * instancia hipotética sin la columna. Memo estático: una lista itera muchas filas y
     * cada Schema::hasColumn lanza una consulta a information_schema; sin el memo
     * pagaríamos una por fila. Schema ya está importado arriba.
     */
    public static function supportsActiveFlag()
    {
        if (self::$activeFlagSupported === null) {
            self::$activeFlagSupported = Schema::hasColumn('hazard_events', 'is_active');
        }
        return self::$activeFlagSupported;
    }

    /**
     * ¿Evento vigente (capturable)? DEGRADE-SAFE: sin la columna, TODO se considera
     * vigente (return true) — así ninguna vista/consulta trata como retirado un evento
     * que en realidad solo carece del delta de BD. Espejo de SafetyStandard::isActive().
     */
    public function isActive()
    {
        return !self::supportsActiveFlag() || (bool) $this->is_active;
    }

    /** Autoridad que validó el evento (sin FK dura; puede ser null si nunca se verificó). */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /** Memo del soporte de verificación en BD (ver supportsVerification()). */
    protected static $verificationSupported = null;

    /**
     * ¿La BD tiene ya el delta de verificación (2026-07-18-normas-eventos-verification.sql)?
     * Memo estático: una lista itera muchas filas y cada Schema::hasColumn lanza una
     * consulta al information_schema; sin el memo pagaríamos una por fila. Schema ya está
     * importado arriba (use Illuminate\Support\Facades\Schema).
     */
    public static function supportsVerification()
    {
        if (self::$verificationSupported === null) {
            self::$verificationSupported = Schema::hasColumn('hazard_events', 'verified_at')
                && Schema::hasColumn('hazard_events', 'verified_by_id');
        }
        return self::$verificationSupported;
    }

    /**
     * Evento ya validado por una autoridad. NO-OP sin el delta aplicado: devuelve false
     * para que la UI no pinte ningún badge ("sin estado de verificación").
     */
    public function isVerified()
    {
        return self::supportsVerification() && $this->verified_at !== null;
    }

    /**
     * Evento capturado a la espera de validación. NO-OP sin el delta aplicado: devuelve
     * false (igual que isVerified) → sin columnas, no hay estado que mostrar.
     */
    public function isPendingVerification()
    {
        return self::supportsVerification() && $this->verified_at === null;
    }

    /**
     * Solo eventos pendientes de verificar. Sin el delta aplicado NADA está pendiente
     * (no existe el concepto), así que el scope corta con un predicado siempre-falso
     * en vez de tronar por columna inexistente.
     */
    public function scopePending($query)
    {
        if (!self::supportsVerification()) {
            return $query->whereRaw('1 = 0');
        }
        return $query->whereNull('verified_at');
    }

    /** Nombre localizado: name_en cuando el locale es 'en' y existe; si no, ES. */
    public function getNameLocalizedAttribute()
    {
        if (app()->getLocale() === 'en' && !empty($this->name_en)) {
            return $this->name_en;
        }
        return $this->name_es;
    }

    /** Etiqueta legible del contexto (localizada). */
    public function getContextLabelAttribute()
    {
        $map = app()->getLocale() === 'en' ? self::contextsEn() : self::contexts();
        return $map[$this->context] ?? $this->context;
    }

    /** Etiqueta legible de la categoría (ES; catálogo cerrado). */
    public function getCategoryLabelAttribute()
    {
        $map = self::categories();
        return $this->category ? ($map[$this->category] ?? $this->category) : null;
    }

    /**
     * Integridad del pivote sin FK dura: al borrar un evento, se purgan sus
     * vínculos con normas (emula ON DELETE CASCADE). Guard defensivo de tabla.
     */
    protected static function booted()
    {
        static::deleting(function ($event) {
            if (Schema::hasTable('hazard_event_standard')) {
                DB::table('hazard_event_standard')
                    ->where('hazard_event_id', $event->id)
                    ->delete();
            }
        });
    }
}
