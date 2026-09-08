<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;
use App\Traits\TracksCorrectiveActions;

/**
 * PERMISO DE TRABAJO EMITIDO (ciclo completo, delta #44).
 *
 * Es un DOCUMENTO CON CONSECUENCIA LEGAL: se emite para una ACTIVIDAD en un SITIO y
 * una JORNADA (la herramienta es lo que lo DISPARA, no su objeto). Se sella con
 * HasDigitalSignatures sobre el DATO (attributesToArray ksorteado), nunca sobre el
 * render, y entra al verificador público con su QR (SealVerifier::TYPES 'perm').
 *
 * CONGELA lo que citó (doctrina cmedic/cédula/acta de inspección): `points_snapshot`,
 * `standards_snapshot`, `permit_*`, `issuer_*`, `acceptor_*` son columnas propias, no
 * relaciones. Si mañana cambia el catálogo, el permiso sigue diciendo lo que dijo y el
 * sello lo prueba.
 *
 * DOS FIRMAS (una sola integridad): EMITE el safety (`issuer_*` + el sello SHA que
 * crea signDocument), ACEPTA el ejecutante designado (`acceptor_*` + `accepted_at`).
 * Ambas identidades quedan DENTRO del hash, así que el sello prueba que ninguna fue
 * alterada; el documento IMPRIME ambas. No se crea una 2ª fila de firma porque el
 * ejecutante puede no tener cuenta: su firma es su identidad congelada + aceptación.
 *
 * ESTADO POSTERIOR AL SELLO (hash-excluido, patrón de retiro de #42): cierre,
 * suspensión y reverificación NO recalculan ni re-firman el hash. El documento
 * cerrado/suspendido sigue ÍNTEGRO — cambió de estado, no de sello.
 */
class IssuedPermit extends Model
{
    use GeneratesUuidKey, TracksCorrectiveActions;
    // Se ALIAS el payload canónico del trait para poder envolverlo (override null-only de `photos`,
    // ver canonicalSignaturePayload() abajo) sin re-implementar la lógica de firma.
    use HasDigitalSignatures {
        canonicalSignaturePayload as baseCanonicalSignaturePayload;
    }

    protected $table = 'issued_permits';

    /** Alcance de sitio (copiado de permits.site_scope; gobierna la vigencia de sitio). */
    const SCOPE_INDIFFERENT = 'indiferente';     // vale por la jornada, sin importar dónde
    const SCOPE_REVERIFY    = 'reverificacion';  // al mover, re-corre SOLO los puntos sensibles
    const SCOPE_SITEBOUND   = 'ligado_al_sitio';  // cambiar de sitio exige emisión nueva

    protected $fillable = [
        'uuid', 'production_id', 'unit_id', 'shoot_day',
        'permit_id', 'permit_code', 'permit_key', 'permit_family', 'permit_name', 'permit_definition',
        'permit_site_scope', 'points_snapshot', 'standards_snapshot', 'photos',
        'activity_description', 'site_label', 'tool_id', 'tool_code', 'tool_name',
        'ext_auth_mandatory', 'ext_auth_authority', 'ext_auth_folio', 'ext_auth_valid_until',
        'ext_auth_declared_by', 'ext_auth_note',
        'issuer_user_id', 'issuer_name', 'issuer_role', 'issuer_cedula',
        'acceptor_user_id', 'acceptor_name', 'acceptor_role', 'acceptor_id_label', 'acceptor_id_value', 'accepted_at',
        'requires_fire_watch', 'fire_watch_confirmed', 'reverifications',
        'closed_at', 'closed_by_id', 'closed_by_name', 'close_notes',
        'suspended_at', 'suspended_by_id', 'suspended_reason', 'superseded_by_id',
        'is_active',
    ];

    protected $casts = [
        'shoot_day'            => 'integer',
        'points_snapshot'      => 'array',
        'standards_snapshot'   => 'array',
        'photos'               => 'array',
        'reverifications'      => 'array',
        'ext_auth_mandatory'   => 'boolean',
        'ext_auth_valid_until' => 'date',
        'accepted_at'          => 'datetime',
        'requires_fire_watch'  => 'boolean',
        'fire_watch_confirmed' => 'boolean',
        'closed_at'            => 'datetime',
        'suspended_at'         => 'datetime',
        'is_active'            => 'boolean',
    ];

    /**
     * FUERA del hash: TODO estado posterior a la emisión. Emitir sella una sola vez sobre
     * el contenido congelado; cerrar, suspender, reverificar o sustituir cambian el ESTADO,
     * nunca la integridad → el verificador nunca los lee como ALTERADO.
     * `accepted_at`, `requires_fire_watch` y `ext_auth_*` SÍ se firman: son contenido fijado
     * al emitir (la aceptación y la autorización externa son parte del documento sellado).
     */
    protected $signatureExcludes = [
        'is_active', 'reverifications', 'fire_watch_confirmed',
        'closed_at', 'closed_by_id', 'closed_by_name', 'close_notes',
        'suspended_at', 'suspended_by_id', 'suspended_reason', 'superseded_by_id',
    ];

    /**
     * (2026-09-05 · Unidades P1) `unit_id` EXCLUIDA del hash SOLO cuando es null. El override de este
     * modelo delega en el método del trait (baseCanonicalSignaturePayload), que lee esta const, así que
     * la exclusión-en-null aplica sin re-implementarla aquí. NO se cablea ningún filtro (eso es Paso 2).
     */
    const NULLABLE_HASH_EXCLUDES = ['unit_id'];

    /**
     * SELLO — override NULL-ONLY de `photos`.
     *
     * Las fotografías adjuntas son CONTENIDO del permiso (adjuntar a un permiso ya sellado una
     * prueba distinta = ALTERADO), así que cuando existen, sus RUTAS entran al hash (misma
     * doctrina que las fotos del DSR / evidence_photos del acta de ambulancia: se sella el path,
     * no los bytes). Por eso `photos` NO va en $signatureExcludes.
     *
     * PERO la columna `photos` se agregó DESPUÉS de que ya había permisos SELLADOS. Si un
     * `photos = null` entrara al payload canónico, el JSON de esos permisos viejos cambiaría
     * (aparecería "photos":null) y su sello se leería como ALTERADO sin que nadie los tocara.
     * Solución: cuando está vacío se RETIRA del payload → los permisos sellados antes de esta
     * columna conservan EXACTAMENTE su hash, y las emisiones nuevas CON fotos SÍ quedan selladas.
     */
    public function canonicalSignaturePayload(): array
    {
        $payload = $this->baseCanonicalSignaturePayload();
        if (empty($payload['photos'])) {
            unset($payload['photos']);
        }
        return $payload;
    }

    // ---- Relaciones (vivas, para lectura; el permiso ya congeló lo que importa) ----
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class, 'permit_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acceptor_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_id');
    }

    /** El permiso emitido que SUSTITUYE a éste (cambio de sitio / re-montaje). */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(IssuedPermit::class, 'superseded_by_id');
    }

    // ---- Estado ----
    /** Abierto = vigente en el eje de estado (ni cerrado ni suspendido ni inactivo). */
    public function isOpen(): bool
    {
        return $this->is_active && $this->closed_at === null && $this->suspended_at === null;
    }

    /** Alias legible para el eje de estado del documento (sin considerar la jornada). */
    public function isVigente(): bool
    {
        return $this->isOpen();
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * PENDIENTE DE CIERRE: emitido, nunca cerrado, y su jornada YA PASÓ. Un permiso emitido
     * y no cerrado al final de la jornada no autoriza nada verificable — cerrarlo es un acto
     * de alguien, no ocurre solo.
     */
    public function isPendingClose($currentShootDay): bool
    {
        return $this->isOpen()
            && $this->shoot_day !== null
            && $currentShootDay !== null
            && $this->shoot_day < $currentShootDay;
    }

    /**
     * ¿Este permiso cubre ESTE sitio? `indiferente` cubre cualquiera; `ligado_al_sitio` solo
     * su sitio de emisión; `reverificacion` cubre el sitio de emisión y los sitios donde una
     * reverificación con resultado 'ok' quedó registrada. Sin sitio dado no se puede negar.
     */
    public function coversSite(?string $siteLabel): bool
    {
        if ($this->permit_site_scope === self::SCOPE_INDIFFERENT) {
            return true;
        }
        if ($siteLabel === null || $siteLabel === '') {
            return true;
        }
        if ($this->site_label === $siteLabel) {
            return true;
        }
        if ($this->permit_site_scope === self::SCOPE_REVERIFY) {
            foreach (($this->reverifications ?? []) as $r) {
                if (($r['result'] ?? null) === 'ok' && ($r['site_label'] ?? null) === $siteLabel) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * El permiso VIGENTE (abierto) de un código para una jornada. Para `ligado_al_sitio` exige
     * que coincida el sitio (un permiso ligado no vale "aquí" si se emitió para otro lugar).
     * Los llamados cruzan la medianoche → se compara el ENTERO shoot_day, no la fecha.
     */
    public static function vigenteFor(string $permitCode, $shootDay, ?string $siteLabel = null, ?string $scope = null): ?self
    {
        $q = self::where('permit_code', $permitCode)
            ->where('is_active', 1)
            ->whereNull('closed_at')
            ->whereNull('suspended_at')
            ->where('shoot_day', $shootDay);

        // (2026-09-07 · Unidades 2b) La vigencia es POR UNIDAD: el número de día colisiona entre unidades
        // (el día 3 de la 2ª unidad no es el día 3 de la principal). Con una sola unidad no filtra →
        // idéntico a hoy. El $shootDay que llega ya es el de la unidad vigente (currentShootDay unit-aware).
        \App\Support\CurrentUnit::applyTo($q);

        if ($scope === self::SCOPE_SITEBOUND && $siteLabel !== null && $siteLabel !== '') {
            $q->where('site_label', $siteLabel);
        }

        return $q->latest('id')->first();
    }

    // ---- Verificador público (Parte B / Paso 5): estado terminal, nunca ALTERADO ----
    /**
     * Estado que lee el verificador público (SealVerifier). NULL si el permiso sigue abierto
     * (se muestra "vigente"); si está CERRADO o SUSPENDIDO, la fecha, la etiqueta del estado y
     * —si existe— el folio del permiso que lo sustituye. El cierre/suspensión NO afecta la
     * integridad del sello: es estado, no alteración. NUNCA se devuelve `suspended_reason`
     * (texto libre): esto lo consume el verificador PÚBLICO. El motivo se ve solo en la página
     * interna (autenticada).
     *
     * @return array{retired_at:?string, retired_label:string, superseded_folio:?string}|null
     */
    public function sealRetirement(): ?array
    {
        $when = null; $label = null;
        if ($this->closed_at !== null) {
            $when = $this->closed_at; $label = 'Cerrado';
        } elseif ($this->suspended_at !== null) {
            $when = $this->suspended_at; $label = 'Suspendido';
        } else {
            return null; // abierto → vigente
        }

        $supersededFolio = null;
        if ($this->superseded_by_id) {
            $sup = self::find($this->superseded_by_id);
            $supersededFolio = $sup ? $sup->folio() : null;
        }

        return [
            'retired_at'       => $when->format('d/m/Y'),
            'retired_label'    => $label,
            'superseded_folio' => $supersededFolio,
        ];
    }

    /** Folio estable para el verificador público y la cadena CFDI. */
    public function folio(): string
    {
        return 'PERM-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
