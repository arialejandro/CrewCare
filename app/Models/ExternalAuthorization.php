<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * DOCUMENTO/autorización externa del proveedor de ambulancias, polimórfico al
 * titular (empresa {@see AmbulanceProvider} o persona {@see AmbulanceCrew}).
 *
 * VALIDACIÓN = DECLARACIÓN MANUAL, calcada de {@see MedicCredential}:
 *   `validated_at` NULL          → PENDIENTE (capturado, nadie cotejó).
 *   `validated_at` + validated_by → validado por esa persona (bajo su responsabilidad).
 * `validation_method` distingue lo que el badge NO debe colapsar:
 *   'documents_reviewed' (hoy siempre: alguien vio el papel) vs
 *   'registry_checked'   (futuro: hubo consulta real a un registro).
 * Los campos de validación NO llegan por POST (no están en $fillable): los escribe
 * solo el flujo de validación. El que captura ≠ el que valida.
 *
 * "En trámite" SIN folio ni fecha compromiso NO es en trámite: es "no lo tiene"
 * ({@see effectiveStatus()}).
 */
class ExternalAuthorization extends Model
{
    protected $table = 'external_authorizations';

    const LEVEL_COMPANY = 'empresa';
    const LEVEL_PERSON  = 'persona';

    const METHOD_DOCS     = 'documents_reviewed';   // hoy siempre
    const METHOD_REGISTRY = 'registry_checked';     // futuro

    const STATUS_PRESENTED = 'presentado';
    const STATUS_PENDING   = 'en_tramite';
    const STATUS_NA        = 'no_aplica';

    // ESTADO REQUERIDO del documento (result_status). Hoy lo consume la 32-D: existir
    // no basta, debe venir POSITIVA. Se captura como dato; no es una validación.
    const RESULT_POSITIVE = 'positiva';
    const RESULT_NEGATIVE = 'negativa';

    // Datos de captura. Los de validación (validated_*, validation_method) quedan
    // FUERA: los escribe solo validate(), nunca el POST del formulario.
    // (2026-08-13 · quien-cobra) `document_type_id` (clave del catálogo), `issued_at`
    // (fecha de emisión) y `result_status` (estado requerido, p.ej. 32-D positiva) SÍ
    // son de captura → entran a $fillable. `document_type` (texto) se conserva por
    // compatibilidad mientras se migran las ambulancias (Paso 5).
    protected $fillable = [
        'holder_type', 'holder_id', 'level',
        'document_type', 'document_type_id', 'authority', 'folio', 'sat_folio',
        'valid_until', 'issued_at', 'photo_path',
        'origen', 'exigido_por', 'is_gate',
        'status', 'result_status', 'pending_commit_date',
        'standard_code', 'standard_name',
        'is_active', 'created_by_id',
        // VENTANA DE RECEPCIÓN — el documento cuelga del PERIODO (además del holder). Se
        // estampan best-effort al capturar; null = recibido sin periodo. `received_out_of_window`
        // marca lo recibido fuera de ventana (nunca rechazado).
        'payment_period_id', 'received_out_of_window',
        // XML DE LA FACTURA (CFDI) — extraídos del XML al recibir (CfdiParser). VERBATIM el total. NADA
        // obligatorio: sin XML quedan NULL y el enlace de la factura simplemente no se puede armar.
        'cfdi_uuid', 'cfdi_rfc_emisor', 'cfdi_rfc_receptor', 'cfdi_total', 'cfdi_sello', 'xml_path',
        'cfdi_conceptos',
    ];

    protected $casts = [
        'valid_until'            => 'date',
        'issued_at'              => 'date',
        'pending_commit_date'    => 'date',
        'is_gate'                => 'boolean',
        'is_active'              => 'boolean',
        'validated_at'           => 'datetime',
        'validated_snapshot'     => 'array',
        'received_out_of_window' => 'boolean',
        'cfdi_conceptos'         => 'array',
    ];

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /** Tipo de documento del catálogo (clave). Reemplaza el texto libre `document_type`. */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /** VENTANA DE RECEPCIÓN — el periodo de pago contra el que se recibió este documento. */
    public function paymentPeriod(): BelongsTo
    {
        return $this->belongsTo(PaymentPeriod::class, 'payment_period_id');
    }

    /**
     * Caducidad EFECTIVA. Prioriza el `valid_until` explícito capturado; si no hay, la
     * DERIVA de la forma de vigencia del tipo + la fecha de emisión (lo que antes nadie
     * calculaba). Devuelve null cuando es permanente o falta el dato para calcular.
     */
    public function effectiveValidUntil(): ?\Carbon\Carbon
    {
        if ($this->valid_until !== null) {
            return $this->valid_until instanceof \Carbon\Carbon
                ? $this->valid_until
                : \Carbon\Carbon::parse($this->valid_until);
        }
        return optional($this->documentType)->expiryFrom($this->issued_at);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_id');
    }

    // ── Estado de validación ──────────────────────────────────────────────────
    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    public function isPending(): bool
    {
        return $this->validated_at === null;
    }

    /** El discriminador que el badge NO debe colapsar (hoy siempre false). */
    public function wasCheckedAgainstRegistry(): bool
    {
        return $this->isValidated() && $this->validation_method === self::METHOD_REGISTRY;
    }

    /**
     * Estado EFECTIVO: "en trámite" sin folio NI fecha compromiso se degrada a
     * "no lo tiene" — no se puede afirmar un trámite sobre la nada.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PENDING) {
            $hasFolio  = trim((string) $this->folio) !== '';
            $hasCommit = $this->pending_commit_date !== null;
            if (! $hasFolio && ! $hasCommit) {
                return 'no_lo_tiene';
            }
        }
        return (string) $this->status;
    }

    /** Texto para la UI: registro vs documentos vs pendiente (nunca se colapsan). */
    public function validationLabel(): string
    {
        if ($this->isPending()) {
            return 'Pendiente de validar';
        }
        $who  = optional($this->validatedBy)->fullName() ?? ($this->validated_snapshot['validated_by'] ?? 'validador no registrado');
        $when = $this->validated_at ? $this->validated_at->format('d/m/Y') : '';
        $verb = $this->wasCheckedAgainstRegistry() ? 'Verificado contra registro' : 'Documentos revisados';
        return trim("{$verb} por {$who}" . ($when !== '' ? " el {$when}" : ''));
    }

    public function photoUrl(): ?string
    {
        $p = trim((string) $this->photo_path);
        return $p !== '' ? Storage::url($p) : null;
    }

    /** ¿Trae los datos del CFDI (extraídos del XML) para armar el enlace de verificación del SAT? */
    public function hasCfdi(): bool
    {
        return trim((string) $this->cfdi_uuid) !== ''
            && trim((string) $this->cfdi_rfc_emisor) !== ''
            && trim((string) $this->cfdi_rfc_receptor) !== ''
            && trim((string) $this->cfdi_total) !== ''
            && trim((string) $this->cfdi_sello) !== '';
    }

    /**
     * ENLACE DE VERIFICACIÓN DE LA FACTURA (CFDI) en el SAT — se ARMA solo desde los datos del XML, sin
     * teclear. 🔴 El servidor NUNCA consulta al SAT: solo devuelve la URL para que un HUMANO la abra y
     * resuelva el captcha. `fe` = últimos 8 del sello del comprobante. `tt` = total VERBATIM del XML (el
     * verificador es quisquilloso con los decimales → no se re-formatea). null si faltan datos CFDI.
     */
    public function satFacturaUrl(): ?string
    {
        if (! $this->hasCfdi()) {
            return null;
        }
        // `fe` = últimos 8 del sello, TAL CUAL (calibrado con un CFDI real: terminan en `==` por el
        // relleno base64 y van así, sin url-encode). Por eso se ARMA la URL en crudo, no con
        // http_build_query (que convertiría `==` en %3D%3D). `tt` = Total VERBATIM del XML. `id` = UUID
        // del TimbreFiscalDigital (lo devuelve CfdiParser, no el del Comprobante).
        $fe = substr(preg_replace('/\s+/', '', (string) $this->cfdi_sello), -8);

        return 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx'
            . '?id=' . $this->cfdi_uuid
            . '&re=' . $this->cfdi_rfc_emisor
            . '&rr=' . $this->cfdi_rfc_receptor
            . '&tt=' . $this->cfdi_total
            . '&fe=' . $fe;
    }

    /**
     * ENLACE DE VERIFICACIÓN DE LA 32-D (Opinión de cumplimiento) en el validador del SAT — se ARMA con el
     * `sat_folio` capturado + el RFC del payee (holder) + la fecha (issued_at) + el sentido (result_status).
     * D3 = `folio_RFC_dd-mm-aaaa_P`. 🔴 El servidor NUNCA consulta al SAT: solo la URL, el captcha lo
     * resuelve un humano. null si falta el folio, el RFC o la fecha.
     */
    public function sat32dUrl(): ?string
    {
        $folio = trim((string) $this->sat_folio);
        $rfc   = strtoupper(trim((string) optional($this->holder)->rfc));
        if ($folio === '' || $rfc === '' || $this->issued_at === null) {
            return null;
        }
        $fecha   = ($this->issued_at instanceof \Carbon\Carbon ? $this->issued_at : \Carbon\Carbon::parse($this->issued_at))->format('d-m-Y');
        $sentido = $this->result_status === self::RESULT_POSITIVE ? 'P' : 'N';
        $d3      = $folio . '_' . $rfc . '_' . $fecha . '_' . $sentido;

        // D1=1&D2=1 (corregido con la URL real del owner; antes asumí D1=10). D3 exacto.
        return 'https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=1&D2=1&D3=' . $d3;
    }

    /** ¿Es la 32-D (Opinión de cumplimiento)? Por el código del tipo de documento. */
    public function is32d(): bool
    {
        return optional($this->documentType)->code === 'OPINION_32D';
    }

    /**
     * Semanas que CUBRE esta factura (Y-m-d, fecha final de cada semana), leídas de las DESCRIPCIONES de
     * los conceptos, no de la fecha del documento. Una factura puede cubrir varias. Distintas, ordenadas.
     * Permite responder "qué se pagó de la semana X" sin depender de cuándo se recibió el documento.
     */
    public function coveredWeeks(): array
    {
        $weeks = [];
        foreach ((array) $this->cfdi_conceptos as $c) {
            $w = is_array($c) ? ($c['week'] ?? null) : null;
            if ($w && ! in_array($w, $weeks, true)) {
                $weeks[] = $w;
            }
        }
        sort($weeks);
        return $weeks;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
