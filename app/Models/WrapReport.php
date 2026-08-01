<?php

namespace App\Models;

use App\Support\CurrentProduction;
use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WrapReport — REPORTE FINAL DE WRAP (2026-07-24).
 *
 * El único documento de la app que no describe un momento sino TODA la producción. Su espina,
 * según SB 132, es la experiencia REAL de riesgo contrastada con las evaluaciones previas: no es
 * un resumen de conteos, es un CONTRASTE PREDICHO vs. REAL.
 *
 * ── ESTA FILA ES UNA FOTOGRAFÍA, NO UNA CONSULTA ────────────────────────────────────────────
 * `payload` guarda las 8 secciones YA CALCULADAS (App\Support\WrapReportBuilder). El documento no
 * se recalcula al abrirlo: se lee tal como se emitió. Dos razones, y la segunda es la que manda:
 *   1. Es lo que un tercero recibió. Un reporte entregado a la casa productora el 30 de julio no
 *      puede cambiar en agosto porque alguien corrigió una foto de un DSR.
 *   2. SIN CONGELAR, EL SELLO SERÍA INÚTIL. El hash se calcula sobre los atributos del modelo; si
 *      el contenido se recalculara en cada lectura, cualquier edición posterior en cualquiera de
 *      los 5 reportes movería los totales y este documento se auto-acusaría de ALTERADO sin que
 *      nadie lo tocara. Una alarma que siempre suena no avisa de nada.
 * Misma doctrina que el snapshot de cédula en `cmedic.medic_cedula`.
 *
 * ── SE SELLA COMO SISTEMA, SIN FIRMANTE ─────────────────────────────────────────────────────
 * El wrap se sella con signDocumentAsSystem(): user_id NULL. No es un descuido, son dos razones:
 *   · El documento es DERIVADO — la app lo calcula desde reportes que ya venían sellados. Nadie
 *     está opinando; se está declarando lo que la base decía. Atribuirlo a la persona que
 *     casualmente apretó el botón sería falsificar el firmante.
 *   · REGLA DE CERO NOMBRES. El recuadro CFDI imprime el nombre de quien firmó. Con user_id NULL
 *     imprime "sello automático del sistema" y el documento cumple, sin excepciones, la promesa
 *     que sostiene al Acto Inseguro: transparencia total en hechos, procesos, decisiones y
 *     consecuencias — nunca en individuos. La firma autógrafa del safety advisor tiene su lugar
 *     en el bloque `.sign` impreso, como en los otros seis documentos.
 *
 * @property int         $id
 * @property int|null    $production_id
 * @property string|null $uuid
 * @property string      $kind         'final' | 'addendum'
 * @property int|null    $parent_id
 * @property array|null  $payload
 */
class WrapReport extends Model
{
    use HasDigitalSignatures;
    use GeneratesUuidKey;

    protected $table = 'wrap_reports';

    protected $fillable = [
        'production_id', 'uuid', 'kind', 'parent_id',
        'period_start', 'period_end', 'reason',
        'payload', 'issued_at', 'issued_by_id',
    ];

    protected $casts = [
        'payload'      => 'array',
        'period_start' => 'date',
        'period_end'   => 'date',
        'issued_at'    => 'datetime',
    ];

    /** Documento de cierre de la producción. */
    const KIND_FINAL = 'final';

    /** Anexo por regrabaciones posteriores (SB 132). NO reemplaza al final. */
    const KIND_ADDENDUM = 'addendum';

    /**
     * Roles Spatie (a nivel usuario) que representan al SAFETY MANAGER.
     *
     * Es el rol que existe en la app para seguridad. Emitir queda para él y para el safety
     * asignado a la producción (ver SAFETY_PIVOT_ROLES) — nadie más, ni siquiera line-producer,
     * que sí tiene dsr.export.
     */
    const SAFETY_ROLES = ['safety-officer'];

    /**
     * Valores de `production_user.role` que marcan a un usuario como SAFETY ASIGNADO A ESTE
     * PROYECTO.
     *
     * Hoy el pivote no trae ninguno (los valores vivos son crew/hod/medic/super-admin), pero el
     * día que el owner asigne un safety por producción, esto lo reconoce SIN tocar código. Es la
     * mitad "safety asignado al proyecto" de la regla: seguridad puede ser una persona distinta en
     * cada producción.
     */
    const SAFETY_PIVOT_ROLES = ['safety', 'safety-officer', 'seguridad'];

    /**
     * ¿Este usuario puede EMITIR (congelar y sellar) el wrap?
     *
     * EMITIR es la acción que congela toda la producción en un documento sellado — la que el owner
     * llama "la bomba". Por eso NO basta con poder exportar reportes (dsr.export lo tiene también
     * line-producer): la emisión queda SÓLO para el SAFETY MANAGER (rol safety-officer) o el SAFETY
     * ASIGNADO A ESTA PRODUCCIÓN (production_user.role de seguridad). El super-admin pasa por ser el
     * operador de la plataforma —misma doctrina que Gate::before en el resto de la app—, no como
     * atajo de producción.
     *
     * ⚠ Esto controla QUIÉN. El CUÁNDO (no antes del wrap) lo controla windowBlockedReason() y es
     *    ABSOLUTO: ni el safety ni el super-admin pueden emitir antes de la fecha de finalización.
     *
     * @param  \App\Models\User|null      $user
     * @param  \App\Models\Production|null $produccion  si es null, se resuelve la vigente
     * @return bool
     */
    public static function issuableBy($user, $produccion = null)
    {
        if (! $user) {
            return false;
        }

        try {
            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('super-admin')) {
                    return true;
                }
                foreach (self::SAFETY_ROLES as $rol) {
                    if ($user->hasRole($rol)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Guard de Spatie no disponible: se sigue al pivote, no se rompe.
        }

        // Safety ASIGNADO a esta producción (mitad forward-compatible de la regla).
        $pid = $produccion ? $produccion->id : CurrentProduction::id();
        if ($pid && Schema::hasTable('production_user')) {
            try {
                return DB::table('production_user')
                    ->where('production_id', $pid)
                    ->where('user_id', $user->id)
                    ->whereIn('role', self::SAFETY_PIVOT_ROLES)
                    ->exists();
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * ¿Por qué NO se puede emitir todavía por FECHA? Devuelve el motivo, o null si la ventana ya
     * está abierta.
     *
     * REGLA DURA DEL OWNER: el wrap NO se emite hasta PASADA la fecha de finalización, para que un
     * clic accidental no dispare el congelamiento antes de tiempo ("que no suceda antes"). Aplica A
     * TODOS, sin excepción de rol — es la protección de la bomba, no un permiso.
     *
     * Se mide contra HOY: la emisión se habilita el día SIGUIENTE a end_date. Sin end_date, se
     * bloquea: no se puede declarar terminada una producción cuyo fin no se ha fijado.
     *
     * end_date es AJUSTABLE (el 3-10% de las producciones se extiende): si se recorre, la ventana
     * se recorre con ella — que es justo lo que se quiere.
     *
     * @param  \Carbon\Carbon|string|null $fin  la fecha de finalización a evaluar
     * @return string|null
     */
    public static function windowBlockedReason($fin)
    {
        if (! $fin) {
            return 'La producción no tiene fecha de finalización definida. El wrap no puede emitirse '
                 . 'hasta que exista y haya pasado, para que un clic accidental no congele la producción '
                 . 'antes de tiempo.';
        }

        $end = Carbon::parse($fin)->startOfDay();
        if (Carbon::today()->lte($end)) {
            return 'El wrap sólo puede emitirse una vez PASADA la fecha de finalización ('
                 . $end->format('d/m/Y') . '). Hasta entonces el documento se puede previsualizar como '
                 . 'borrador, pero no emitirse ni sellarse. Si la producción se extiende o termina antes, '
                 . 'ajusta la fecha de finalización.';
        }

        return null;
    }

    /**
     * ¿Está aplicado el SQL del módulo? Memo estático: se pregunta una vez por petición.
     *
     * Sin la tabla, el módulo entero se apaga (menú, rutas, botón) en vez de tronar. Mismo patrón
     * que MedicCredential::supportsCredentials() y SfxEffectType::supportsStandardsBridge().
     *
     * @return bool
     */
    public static function supported()
    {
        static $memo = null;
        if ($memo === null) {
            try {
                $memo = Schema::hasTable('wrap_reports');
            } catch (\Throwable $e) {
                $memo = false;
            }
        }
        return $memo;
    }

    /** Producción que cierra este reporte. Referencia blanda: puede quedar huérfana. */
    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /** Documento final al que complementa este anexo (null si es el final). */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Anexos emitidos sobre este documento final. */
    public function addendums()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    /** ¿Es un anexo? */
    public function isAddendum()
    {
        return $this->kind === self::KIND_ADDENDUM;
    }

    /**
     * Folio legible del documento. El prefijo distingue el cierre del anexo a simple vista, que es
     * como se van a citar en papel ("ver WRAP-0001" / "anexo WRAPA-0002").
     *
     * @return string
     */
    public function folio()
    {
        $prefijo = $this->isAddendum() ? 'WRAPA' : 'WRAP';
        return $prefijo . '-' . str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Sección del payload congelado, con respaldo vacío.
     *
     * La vista NUNCA toca $this->payload['x']['y'] a pelo: un payload de una versión anterior del
     * builder tendría claves distintas y el documento reventaría al abrirse — justo el documento
     * que existe para poder abrirse años después.
     *
     * @param  string $clave
     * @param  mixed  $default
     * @return mixed
     */
    public function seccion($clave, $default = [])
    {
        $p = $this->payload;
        if (! is_array($p) || ! array_key_exists($clave, $p)) {
            return $default;
        }
        return $p[$clave];
    }
}
