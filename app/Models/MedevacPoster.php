<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * MedevacPoster — PÓSTER MEDEVAC emitido (delta #46, 2026-07-31).
 *
 * La PRIMERA plantilla del motor de salida de documentos. NO es captura nueva: se
 * RENDERIZA desde lo que el SCOUTING ya tiene (hospital, dirección, ETA, distancia,
 * GPS, punto de reunión, acceso de emergencia) más los CONTACTOS de emergencia.
 *
 * ── ESTA FILA ES UNA FOTOGRAFÍA, NO UNA CONSULTA (doctrina WrapReport/cmedic) ────
 * `payload` guarda la locación y los contactos YA RESUELTOS al emitir (ver
 * App\Support\MedevacPosterBuilder). El documento no se recalcula al abrirlo: se lee
 * tal como se emitió. Si mañana cambia el scouting, el póster sigue diciendo lo que
 * dijo y el sello SHA-256 lo prueba. Sin congelar, el sello sería inútil.
 *
 * ── EMISIONES INDEPENDIENTES (decisión del owner) ───────────────────────────────
 * Cada emisión es un documento NUEVO y SIN RELACIÓN con los anteriores: sin cadena de
 * versiones, sin vínculo "sustituye a". `revision` es un CONSECUTIVO POR LOCACIÓN
 * (cuenta los pósters previos de esa locación + 1); dice "este es el N-ésimo póster de
 * esta locación", no encadena. Por eso el póster NO implementa sealRetirement(): el
 * verificador lo lee en dos estados (vigente / alterado), nunca "retirado".
 *
 * ── SELLO ───────────────────────────────────────────────────────────────────────
 * Se firma sobre el DATO (attributesToArray ksorteado: payload + revision + issued_*),
 * nunca sobre el render. `is_active` queda FUERA del hash: es estado de listado, no
 * contenido (y nunca se borra con ->delete(); se apaga con is_active).
 *
 * REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent.
 */
class MedevacPoster extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey;

    protected $table = 'medevac_posters';

    protected $fillable = [
        'uuid', 'production_id', 'scouting_report_id',
        'revision', 'location_label', 'payload',
        'issued_by_id', 'issued_by_name', 'issued_at', 'is_active',
    ];

    protected $casts = [
        'payload'   => 'array',
        'issued_at' => 'datetime',
        'is_active' => 'boolean',
        'revision'  => 'integer',
    ];

    /**
     * FUERA del hash: `is_active` es estado de listado posterior a la emisión, no
     * contenido sellado. Apagar un póster (is_active=0) no lo marca ALTERADO.
     */
    protected $signatureExcludes = ['is_active'];

    /**
     * (2026-09-05 · Unidades P1) `unit_id` EXCLUIDA del hash SOLO cuando es null: los 6 pósters sellados
     * la traen en null → su sello NO cambia; con valor (2ª unidad) SÍ se sella. La aplica el trait
     * (HasDigitalSignatures::nullableHashExcludes). NO se cablea ningún filtro por unidad (eso es Paso 2).
     */
    const NULLABLE_HASH_EXCLUDES = ['unit_id'];

    /**
     * ¿Está aplicado el SQL del módulo? Memo por petición. Sin la tabla, el módulo se
     * apaga (menú/rutas/botón) en vez de tronar. Mismo patrón que WrapReport::supported().
     */
    public static function supported(): bool
    {
        static $memo = null;
        if ($memo === null) {
            try {
                $memo = Schema::hasTable('medevac_posters');
            } catch (\Throwable $e) {
                $memo = false;
            }
        }
        return $memo;
    }

    // ---- Relaciones (vivas, para lectura; el póster ya congeló lo que importa) ----
    public function scouting(): BelongsTo
    {
        return $this->belongsTo(ScoutingReport::class, 'scouting_report_id');
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /** Folio estable para el verificador público y la cadena CFDI. */
    public function folio(): string
    {
        return 'MDVC-' . str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Clave del payload congelado, con respaldo (patrón WrapReport::seccion): la vista NUNCA
     * toca $this->payload['x'] a pelo, para que un payload de otra versión no reviente el
     * documento que existe justamente para poder abrirse años después.
     */
    public function pdata($key, $default = null)
    {
        $p = $this->payload;
        if (! is_array($p) || ! array_key_exists($key, $p)) {
            return $default;
        }
        return $p[$key];
    }

    /**
     * Revisión que le toca a esta emisión para su LOCACIÓN.
     *
     * NO es un contador de clicks: es la revisión del PROTOCOLO. Sólo sube cuando cambió el
     * contenido que viene del SCOUTING (hospital, ruta, distancia, ETA, puntos de emergencia…).
     * Reemitir/reimprimir sin tocar el scouting —aunque cambien los contactos o el mapa que
     * adjunta el emisor— CONSERVA el número: exportar dos veces el mismo PDF no son "dos
     * versiones". Se congela al emitir; el sello prueba qué decía esa revisión.
     *
     * Se compara contra el ÚLTIMO póster de la misma locación (nombre EXACTO dentro de la
     * producción; si no hay etiqueta, el scouting de origen). Igualdad exacta a propósito,
     * nunca parecido difuso: un cruce falso es peor que un hueco.
     *
     * @param  array $payload  el payload YA construido por MedevacPosterBuilder para esta emisión
     */
    public static function revisionFor($productionId, ?string $locationLabel, $scoutingId, array $payload): int
    {
        $last = self::locationQuery($productionId, $locationLabel, $scoutingId)
            ->orderByDesc('id')->first();

        if (! $last) {
            return 1;   // primera emisión de esta locación
        }

        // ¿Cambió lo que vino del scouting desde el último póster? (contactos, mapa y marca NO
        // cuentan: no son "el scouting".) Sin cambios → se conserva la revisión anterior.
        if (self::scoutingFingerprint((array) $last->payload) === self::scoutingFingerprint($payload)) {
            return (int) $last->revision;
        }

        return ((int) self::locationQuery($productionId, $locationLabel, $scoutingId)->max('revision')) + 1;
    }

    /**
     * Alcance "misma locación" para revisión/historial: nombre EXACTO dentro de la producción
     * (así una re-scouteada del mismo sitio sigue la numeración); si no hay etiqueta, cae al
     * scouting de origen.
     */
    private static function locationQuery($productionId, ?string $locationLabel, $scoutingId)
    {
        $q = self::query();
        $label = trim((string) $locationLabel);
        if ($label !== '') {
            $q->where('location_label', $label);
            if ($productionId) {
                $q->where('production_id', $productionId);
            }
        } else {
            $q->where('scouting_report_id', $scoutingId);
        }
        return $q;
    }

    /**
     * Huella del CONTENIDO DEL SCOUTING dentro del payload (decide si la revisión sube). Sólo
     * los campos que vienen del scouting; NO contactos, NO mapa, NO marca (esos son decisiones
     * de la emisión o config, no cambios del scouting). Orden de claves FIJO → hash estable.
     */
    private static function scoutingFingerprint(array $payload): string
    {
        $keys = [
            'location', 'gps', 'maps_url', 'distance_km', 'eta', 'hospital',
            'ambulance_company', 'emergency_phone', 'assembly_point', 'emergency_access',
        ];
        $subset = [];
        foreach ($keys as $k) {
            $subset[$k] = $payload[$k] ?? null;
        }
        return hash('sha256', (string) json_encode($subset));
    }
}
