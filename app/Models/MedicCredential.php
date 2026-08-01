<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Support\SepRegistry;

/**
 * MedicCredential — la CÉDULA PROFESIONAL de un médico (Paso B, 2026-07-19).
 *
 * QUÉ ES: ata cada firma médica a una licencia verificable. Es la pieza que permite
 * deslindar responsabilidades: sin ella, "lo atendió un médico" es una afirmación sin
 * respaldo; con ella, es una licencia con número, registro y rastro de quién la cotejó.
 *
 * CUELGA DE `users`, NO ES UNA COLUMNA SUELTA: una cédula tiene estado propio
 * (verificada/pendiente), autoría, fecha y evidencia. Eso es una entidad, no un campo.
 *
 * SOLO APLICA A USUARIOS CON ROL `medic` — y eso se valida en el CONTROLADOR, no aquí:
 * la base de datos no sabe de roles de Spatie, y una FK no puede expresar "solo si tiene
 * tal rol". La invariante vive donde puede comprobarse de verdad.
 *
 * ── LOS DOS NULL, QUE AQUÍ NO SIGNIFICAN LO MISMO QUE EN EL RESTO DEL REPO ──────────
 *   - verified_at NULL                          → PENDIENTE. Capturada, nadie la cotejó.
 *   - verified_at con fecha + verified_by_id NULL → la validó el enganche AUTOMÁTICO a la
 *     SEP (sin autor humano). Hoy no ocurre: queda listo para el sub-paso diferido.
 *   - verified_at con fecha + verified_by_id      → la validó esa persona.
 *
 * A DIFERENCIA de `consumables` / `safety_standards`, aquí NO hay "verificada de origen":
 * no existe catálogo semilla de cédulas. Ninguna se da por buena sin que alguien —o el
 * registro oficial— la valide. Un sello de origen sería exactamente la mentira que este
 * módulo existe para impedir.
 *
 * DEGRADACIÓN: sin el SQL aplicado (database/owner-apply/2026-07-19-medic-credentials.sql)
 * la tabla no existe y todo el módulo desaparece de la UI sin romper nada — igual que el
 * módulo SDS. Ver supportsCredentials().
 *
 * PRIVACIDAD: la cédula y el nombre registrado son dato PROFESIONAL (público en el registro
 * de la SEP), no dato de salud del médico. Aun así se tratan con el cuidado del silo médico:
 * no se exponen a roles sin `medical.view`.
 */
class MedicCredential extends Model
{
    protected $table = 'medic_credentials';

    /** Fuentes de verificación admitidas. El enum cerrado vive aquí, no en el esquema. */
    const SOURCE_MANUAL   = 'manual';
    const SOURCE_SEP_AUTO = 'sep_auto';

    /**
     * OJO: `verified_at`, `verified_by_id`, `verification_source` y `verified_snapshot`
     * están en el fillable pero NUNCA llegan por POST — las reglas de validación del
     * controlador no las incluyen; solo las escribe el flujo de verificación del servidor.
     * (Misma convención que Consumable / SafetyStandard.)
     */
    protected $fillable = [
        'user_id',
        'cedula',
        'profession',
        'specialty',
        'registered_name',
        'verification_url',
        'verification_source',
        'verified_snapshot',
        'verified_at',
        'verified_by_id',
    ];

    protected $casts = [
        'verified_at'       => 'datetime',
        'verified_snapshot' => 'array',
    ];

    // ---------------------------------------------------------------------------
    // Relaciones
    // ---------------------------------------------------------------------------

    /** El médico dueño de la cédula. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quién validó la cédula. Sin FK dura (convención del repo: no hay ni una FK a `users`
     * en owner-apply). Puede ser null en dos casos MUY distintos — ver el docblock de la
     * clase: nunca se verificó, o la verificó el enganche automático a la SEP.
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    // ---------------------------------------------------------------------------
    // Disponibilidad del módulo
    // ---------------------------------------------------------------------------

    /** Memo del soporte en BD (ver supportsCredentials()). */
    protected static $credentialsSupported = null;

    /**
     * ¿La BD tiene ya la tabla (2026-07-19-medic-credentials.sql)?
     *
     * Memo estático: los listados de crew y de consultas preguntan esto una vez POR FILA,
     * y cada Schema::hasTable lanza una consulta al information_schema. Sin el memo
     * pagaríamos una por fila. Se inicializa a `null` y no a `false` a propósito: hay que
     * poder distinguir "aún no consultado" de "no soportado".
     */
    public static function supportsCredentials()
    {
        if (self::$credentialsSupported === null) {
            self::$credentialsSupported = Schema::hasTable('medic_credentials');
        }
        return self::$credentialsSupported;
    }

    // ---------------------------------------------------------------------------
    // Estado de verificación
    // ---------------------------------------------------------------------------

    /**
     * Cédula cotejada contra el registro oficial. NO-OP sin la tabla: devuelve false para
     * que la UI no pinte ningún badge ("sin estado de verificación" es un tercer estado
     * legítimo, distinto de "pendiente").
     */
    public function isVerified()
    {
        return self::supportsCredentials() && $this->verified_at !== null;
    }

    /**
     * Cédula capturada a la espera de cotejo. NO-OP sin la tabla, igual que isVerified()
     * → sin tabla no hay estado que mostrar, y el par @if/@elseif de las vistas no pinta
     * nada. Eso es lo que hace segura la degradación.
     */
    public function isPendingVerification()
    {
        return self::supportsCredentials() && $this->verified_at === null;
    }

    /** Solo cédulas pendientes de cotejar. Sin la tabla, NADA está pendiente. */
    public function scopePending($query)
    {
        if (!self::supportsCredentials()) {
            return $query->whereRaw('1 = 0');
        }
        return $query->whereNull('verified_at');
    }

    /** ¿La validó una persona (y no el enganche automático)? */
    public function wasVerifiedByHuman()
    {
        return $this->isVerified() && $this->verified_by_id !== null;
    }

    /**
     * Procedencia del sello, en texto listo para pintar. Los tres NULL/valores del
     * docblock de la clase, resueltos en un solo sitio para que las vistas no repitan
     * la lógica (ni la interpreten mal).
     *
     * @return string|null  null si no está verificada.
     */
    public function verificationSourceLabel()
    {
        if (!$this->isVerified()) {
            return null;
        }

        if ($this->verification_source === self::SOURCE_SEP_AUTO) {
            return 'Verificada automáticamente contra el registro de la SEP';
        }

        // `manual` (o valor legado/ausente): hubo una persona. Si su usuario se borró,
        // la relación viene null pero el id sigue ahí — no se colapsan en un ??.
        $verifier = $this->verifiedBy;
        $name     = $verifier !== null ? trim((string) $verifier->name) : '';

        if ($this->verified_by_id === null) {
            // Verificada sin autor y sin marca de automático: dato anómalo, no se inventa autor.
            return 'Verificada (sin autor registrado)';
        }

        return 'Verificada por ' . ($name !== '' ? $name : 'usuario dado de baja');
    }

    // ---------------------------------------------------------------------------
    // Enlace de cotejo
    // ---------------------------------------------------------------------------

    /**
     * El enlace de cotejo, YA SANEADO, o null.
     *
     * DOBLE lista blanca (esquema http/https + dominio oficial de la SEP) delegada en
     * App\Support\SepRegistry. Las vistas deben pintar el <a> SOLO si esto no es null:
     * `verification_url` es texto que escribió una persona y no es de fiar. Un dominio
     * ajeno se muestra como texto plano, nunca como enlace.
     *
     * @return string|null
     */
    public function safeVerificationUrl()
    {
        return SepRegistry::safeHref($this->verification_url);
    }

    /**
     * ¿El nombre del registro coincide con el del titular en la app?
     *
     * Es la comprobación ANTISUPLANTACIÓN. Devuelve false si falta cualquiera de los dos
     * (no se puede afirmar coincidencia sobre la nada, y de esto depende encender un badge
     * legal: ante la duda, false).
     */
    public function registeredNameMatchesUser()
    {
        $owner = $this->user;
        if ($owner === null) {
            return false;
        }

        return SepRegistry::namesMatch($this->registered_name, $owner->fullName());
    }
}
