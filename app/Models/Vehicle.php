<?php

namespace App\Models;

use App\Support\VehicleChecklist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * ENTIDAD VEHÍCULO — reutilizable dentro de la instancia. Promueve payee_contracts.asset_ref.
 *
 * SIN ENTIDADES PARALELAS:
 *   - driver = CREW → {@see driver()} liga a users (bidireccional, navegable en ambos sentidos).
 *   - propietario PROVEEDOR → {@see ownerPayee()} liga a payees (con su paquete documental).
 *   - propietario PARTICULAR → {@see ownerUser()} (persona en el sistema) o `owner_name` suelto.
 *
 * Los DOCUMENTOS del vehículo (tarjeta/póliza/verificación/licencia) cuelgan del ledger
 * polimórfico external_authorizations ({@see documents()}) con su tipo del catálogo document_types
 * y vigencia por fecha. Los docs FISCALES del proveedor NO se duplican: viven en el payee.
 */
class Vehicle extends Model
{
    protected $table = 'vehicles';

    const OWNER_PROVIDER = 'provider';
    const OWNER_PERSON   = 'person';
    const OWNER_OTHER    = 'other';

    /**
     * Documentos DEL VEHÍCULO exigidos para la marca "Documentos revisados" (§4).
     * La LICENCIA del conductor (VEH_LICENCIA) NO va aquí: vive en el paquete documental del DRIVER
     * (payees), junto a su 32-D/CSF/comprobante, para consulta de contabilidad. En la ficha se
     * MUESTRA (no se copia) vía {@see driverLicense()}.
     */
    const REQUIRED_DOC_CODES = ['VEH_TARJETA', 'VEH_POLIZA', 'VEH_VERIFICACION'];

    protected $fillable = [
        'vehicle_type_id', 'type_code',
        'make', 'model', 'year', 'color', 'plate', 'vin',
        'attr_values',
        'owner_kind', 'owner_payee_id', 'owner_user_id', 'owner_name',
        'driver_user_id', 'initial_km', 'notes',
        'is_active', 'created_by_id',
    ];

    protected $casts = [
        'attr_values' => 'array',
        'year'        => 'integer',
        'initial_km'  => 'integer',
        'is_active'   => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    // ── Relaciones (FK-soft) ──────────────────────────────────────────────────
    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function ownerPayee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'owner_payee_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** DRIVER = CREW. La relación es bidireccional (User también puede navegar sus vehículos). */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    /** Documentos del vehículo (polimórficos: holder = Vehicle). Tarjeta/póliza/verificación/licencia. */
    public function documents(): MorphMany
    {
        return $this->morphMany(ExternalAuthorization::class, 'holder');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class, 'vehicle_id')->orderBy('created_at', 'desc');
    }

    // ── Atributos resueltos (perfil del tipo + ajustes de la unidad) ──────────
    public function resolvedAttributes(): array
    {
        $base = $this->type ? $this->type->profile() : VehicleChecklist::normalizeAttributes([]);
        return VehicleChecklist::normalizeAttributes(array_merge($base, (array) ($this->attr_values ?? [])));
    }

    // ── Propietario / etiqueta ────────────────────────────────────────────────
    public function ownerLabel(): ?string
    {
        if ($this->owner_kind === self::OWNER_PROVIDER && $this->ownerPayee) {
            return $this->ownerPayee->name;
        }
        if ($this->owner_kind === self::OWNER_PERSON && $this->ownerUser) {
            return User::displayName($this->ownerUser);
        }
        $n = trim((string) $this->owner_name);
        return $n !== '' ? $n : null;
    }

    public function driverLabel(): ?string
    {
        return $this->driver ? User::displayName($this->driver) : null;
    }

    // ── Licencia del conductor (§2): vive en el PAQUETE del driver (payees), aquí se MUESTRA ──
    /** El payee (paquete documental) del conductor asignado, o null. Vínculo 1:1 por convención. */
    public function driverPayee(): ?Payee
    {
        if (! $this->driver_user_id) {
            return null;
        }
        return Payee::where('user_id', $this->driver_user_id)->first();
    }

    /**
     * La LICENCIA del conductor asignado, resuelta de su paquete de payee (holder=Payee,
     * document_type VEH_LICENCIA), la más reciente. Read-only: NO se copia al vehículo.
     */
    public function driverLicense(): ?ExternalAuthorization
    {
        $payee = $this->driverPayee();
        if (! $payee) {
            return null;
        }
        $typeId = self::licenseTypeId();
        if (! $typeId) {
            return null;
        }
        return $payee->documents()
            ->where('document_type_id', $typeId)
            ->orderByDesc('id')
            ->first();
    }

    /** id del tipo de documento VEH_LICENCIA (cacheado por petición). */
    public static function licenseTypeId(): ?int
    {
        static $id = false;
        if ($id === false) {
            $dt = DocumentType::where('code', 'VEH_LICENCIA')->first();
            $id = $dt ? (int) $dt->id : null;
        }
        return $id;
    }

    // ── Marca 1: INSPECCIONADO (checklist aprobado) ───────────────────────────
    public function latestInspection(): ?VehicleInspection
    {
        if ($this->relationLoaded('inspections')) {
            // inspections() ya viene ordenada desc → el primer activo es el más reciente.
            return $this->inspections->firstWhere('is_active', true);
        }
        return $this->inspections()->where('is_active', 1)->latest('id')->first();
    }

    /** Estado de inspección para el badge/lista: 'apto' | 'no_apto' | 'pending' (nunca inspeccionado). */
    public function inspectionState(): string
    {
        $acta = $this->latestInspection();
        if (! $acta) {
            return 'pending';
        }
        return $acta->isApto() ? 'apto' : 'no_apto';
    }

    public function inspectedAt()
    {
        $acta = $this->latestInspection();
        return ($acta && $acta->isApto()) ? $acta->created_at : null;
    }

    // ── Marca 2: DOCUMENTOS REVISADOS (alguien vio el papel y lo declaró) ──────
    /**
     * Documentos VALIDADOS y VIGENTES por código de tipo requerido. Devuelve un mapa
     * code => ExternalAuthorization|null. Un doc caducado cuenta como faltante (se vuelve a pedir).
     *
     * @return array<string, \App\Models\ExternalAuthorization|null>
     */
    public function requiredDocState(): array
    {
        $today = now()->startOfDay();
        $docs  = $this->relationLoaded('documents')
            ? $this->documents
            : $this->documents()->with('documentType')->get();

        $out = array_fill_keys(self::REQUIRED_DOC_CODES, null);
        foreach ($docs as $doc) {
            $code = optional($doc->documentType)->code;
            if ($code === null || ! array_key_exists($code, $out)) {
                continue;
            }
            if (! $doc->isValidated()) {
                continue;
            }
            $until = method_exists($doc, 'effectiveValidUntil') ? $doc->effectiveValidUntil() : $doc->valid_until;
            $vigente = ($until === null) || ($until >= $today); // sin fecha = permanente = vigente
            if ($vigente) {
                // Nos quedamos con el más reciente validado y vigente.
                if ($out[$code] === null || $doc->validated_at >= $out[$code]->validated_at) {
                    $out[$code] = $doc;
                }
            }
        }
        return $out;
    }

    public function docsReviewed(): bool
    {
        foreach ($this->requiredDocState() as $doc) {
            if ($doc === null) {
                return false;
            }
        }
        return true;
    }

    public function docsReviewedAt()
    {
        $latest = null;
        foreach ($this->requiredDocState() as $doc) {
            if ($doc === null) {
                return null; // falta alguno → no hay marca
            }
            if ($latest === null || $doc->validated_at >= $latest) {
                $latest = $doc->validated_at;
            }
        }
        return $latest;
    }
}
