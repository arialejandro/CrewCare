<?php

namespace App\Http\Controllers;

use App\Models\AmbulanceCrew;
use App\Models\AmbulanceDayResource;
use App\Models\AmbulanceInspection;
use App\Models\AmbulanceProvider;
use App\Models\AmbulanceType;
use App\Models\ExternalAuthorization;
use App\Models\MedicCredential;
use App\Models\User;
use App\Support\AmbulanceVerdict;
use App\Support\CurrentProduction;
use App\Support\ImageCompressor;
use App\Support\ProductionCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VERIFICACIÓN DE RECURSO DE EMERGENCIA EN SITIO. Hermana de {@see InspectionController}:
 * elegir tipo/disparador → verificar → veredicto derivado del dato → acta sellada.
 *
 * ENCUADRE (importa que la UI lo diga): es constancia de VERIFICACIÓN DE RECURSO DE
 * EMERGENCIA EN SITIO, NO inspección sanitaria (eso lo hace la autoridad). Registrar
 * que el recurso está listo no sustituye el dictamen oficial.
 *
 * Gate: permission:ambulance.manage a nivel de RUTA (no se agrega middleware aquí,
 * igual que InspectionController). El verificador público del acta vive aparte
 * (SealVerifier, tipo 'ambu'), sin sesión.
 */
class AmbulanceController extends Controller
{
    /* ========================= HUB ========================= */

    /** Panel: recurso de traslado del día, proveedores activos y actas recientes. */
    public function index(Request $request)
    {
        $productionId = CurrentProduction::id();
        $shootDay     = $this->currentShootDay();

        $dayResource = AmbulanceDayResource::active()
            ->where('production_id', $productionId)
            ->where('shoot_day', $shootDay)
            ->with(['provider', 'inspection'])
            ->latest('id')
            ->first();

        $providers = AmbulanceProvider::active()->orderBy('name')->get();

        $inspections = AmbulanceInspection::query()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('ambulance.index', compact('dayResource', 'providers', 'inspections', 'shootDay'));
    }

    /* ================= RECURSO DE TRASLADO DEL DÍA ================= */

    /** Formulario del recurso del día (crea o edita el del production+shoot_day actual). */
    public function dayResourceForm(Request $request)
    {
        $productionId = CurrentProduction::id();
        $shootDay     = $this->currentShootDay();

        $resource = AmbulanceDayResource::active()
            ->where('production_id', $productionId)
            ->where('shoot_day', $shootDay)
            ->latest('id')
            ->first();

        $providers = AmbulanceProvider::active()->orderBy('name')->get();

        // Acta VIGENTE del día → alimenta el estado 1 (hay ambulancia en sitio).
        $vigente = $this->vigenteInspectionForDay($productionId, $shootDay);

        return view('ambulance.day-resource', compact('resource', 'providers', 'vigente', 'shootDay'));
    }

    /**
     * Declara el recurso del día. Tres estados; elegir 'declared_medium' (medio de
     * producción declarado) NO es error ni advertencia — es un arreglo legítimo. Solo
     * ese estado exige medio + tiempo de respuesta (servicio/teléfono se recomiendan).
     */
    public function storeDayResource(Request $request)
    {
        $data = $request->validate([
            'state'                   => 'required|in:' . implode(',', AmbulanceDayResource::STATES),
            'provider_id'             => 'nullable|integer|exists:ambulance_providers,id',
            'ambulance_inspection_id' => 'nullable|integer|exists:ambulance_inspections,id',
            'transport_means'         => 'nullable|string|max:255',
            'call_service'            => 'nullable|string|max:255',
            'call_phone'              => 'nullable|string|max:60',
            'response_time'           => 'nullable|string|max:120',
            'notes'                   => 'nullable|string|max:2000',
        ]);

        // El medio declarado exige QUÉ vehículo y EN CUÁNTO llega el apoyo grave.
        if ($data['state'] === AmbulanceDayResource::STATE_MEDIUM) {
            $request->validate([
                'transport_means' => 'required|string|max:255',
                'response_time'   => 'required|string|max:120',
            ], [], [
                'transport_means' => 'medio de traslado',
                'response_time'   => 'tiempo de respuesta',
            ]);
        }

        $isAmbulance = $data['state'] === AmbulanceDayResource::STATE_AMBULANCE;
        $isMedium    = $data['state'] === AmbulanceDayResource::STATE_MEDIUM;

        $productionId = CurrentProduction::id();
        $shootDay     = $this->currentShootDay();
        $author       = auth()->user();

        // Cada estado conserva SOLO sus campos (no arrastrar datos de un estado a otro).
        $payload = [
            'resource_date'           => now()->toDateString(),
            'state'                   => $data['state'],
            'provider_id'             => $isAmbulance ? ($data['provider_id'] ?? null) : null,
            'ambulance_inspection_id' => $isAmbulance ? ($data['ambulance_inspection_id'] ?? null) : null,
            'transport_means'         => $isMedium ? ($data['transport_means'] ?? null) : null,
            'call_service'            => $isMedium ? ($data['call_service'] ?? null) : null,
            'call_phone'              => $isMedium ? ($data['call_phone'] ?? null) : null,
            'response_time'           => $isMedium ? ($data['response_time'] ?? null) : null,
            'notes'                   => $data['notes'] ?? null,
            // Se congela quién y cuándo lo declaró (el criterio queda fijado ANTES).
            'declared_by_id'          => $author ? $author->id : null,
            'declared_by_name'        => $author ? User::displayName($author) : null,
            'declared_at'             => now(),
            'is_active'               => 1,
        ];

        AmbulanceDayResource::updateOrCreate(
            ['production_id' => $productionId, 'shoot_day' => $shootDay],
            $payload
        );

        return redirect()->route('ambulance.index')
            ->with('success', 'Recurso de traslado del día registrado.');
    }

    /* ===================== PROVEEDORES ===================== */

    /** Lista de proveedores + formulario de alta. */
    public function providers(Request $request)
    {
        $providers = AmbulanceProvider::active()
            ->withCount('crew')
            ->orderBy('name')
            ->get();

        return view('ambulance.providers', compact('providers'));
    }

    /** Alta de proveedor (empresa prestadora). Se califica la primera vez que se necesita. */
    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'rfc'              => 'nullable|string|max:20',
            'contact_phone'    => 'nullable|string|max:60',
            'sanitary_manager' => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $author = auth()->user();

        $provider = AmbulanceProvider::create(array_merge($data, [
            'is_active'     => 1,
            'created_by_id' => $author ? $author->id : null,
        ]));

        return redirect()->route('ambulance.provider.show', $provider)
            ->with('success', 'Proveedor registrado.');
    }

    /** Ficha del proveedor: padrón (crew) + documentos de empresa + documentos por persona. */
    public function providerShow(AmbulanceProvider $provider)
    {
        // Padrón activo, cada tripulante con sus documentos de nivel persona.
        $crew = $provider->crew()->active()
            ->with(['authorizations' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->orderBy('full_name')
            ->get();

        // Documentos de nivel EMPRESA.
        $companyDocs = $provider->authorizations()
            ->where('level', ExternalAuthorization::LEVEL_COMPANY)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('ambulance.provider-show', compact('provider', 'crew', 'companyDocs'));
    }

    /** Alta de tripulante EN EL MOMENTO (sin fricción), con foto de credencial opcional. */
    public function storeCrew(Request $request, AmbulanceProvider $provider)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'crew_role' => 'nullable|string|max:120',
            'id_photo'  => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
            'notes'     => 'nullable|string|max:2000',
        ]);

        $author = auth()->user();

        $photoPath = $request->hasFile('id_photo')
            ? ImageCompressor::store($request->file('id_photo'), 'ambulance/crew')
            : null;

        AmbulanceCrew::create([
            'provider_id'   => $provider->id,
            'full_name'     => $data['full_name'],
            'crew_role'     => $data['crew_role'] ?? null,
            'id_photo_path' => $photoPath,
            'notes'         => $data['notes'] ?? null,
            'is_active'     => 1,
            'created_by_id' => $author ? $author->id : null,
        ]);

        return redirect()->route('ambulance.provider.show', $provider)
            ->with('success', 'Tripulante agregado al padrón.');
    }

    /* ===================== DOCUMENTOS / AUTORIZACIONES ===================== */

    /**
     * Captura un documento/autorización externa polimórfico (empresa o persona). NACE
     * PENDIENTE: los campos validated_* NO se tocan aquí (no están en fillable). Capturar
     * no es cotejar — la validación es otra acción, con otra autoridad. Espejo de la cédula.
     */
    public function storeDocument(Request $request)
    {
        $data = $request->validate([
            'holder_type'         => 'required|in:empresa,persona',
            'holder_id'           => 'required|integer',
            'document_type'       => 'required|string|max:255',
            'authority'           => 'nullable|string|max:255',
            'folio'               => 'nullable|string|max:120',
            'valid_until'         => 'nullable|date',
            'origen'              => 'required|in:normativo,contractual,recomendado',
            'exigido_por'         => 'nullable|string|max:255',
            'is_gate'             => 'nullable|boolean',
            'status'              => 'required|in:presentado,en_tramite,no_aplica',
            'pending_commit_date' => 'nullable|date',
            'standard_code'       => 'nullable|string|max:120',
            'standard_name'       => 'nullable|string|max:255',
            'photo'               => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
        ]);

        // La clave corta del titular se resuelve a la CLASE real (morph) y se comprueba
        // que exista: nunca se inventa el vínculo.
        $holderMap = [
            'empresa' => AmbulanceProvider::class,
            'persona' => AmbulanceCrew::class,
        ];
        $holderClass = $holderMap[$data['holder_type']];
        $holder      = $holderClass::find($data['holder_id']);
        if ($holder === null) {
            return back()->withInput()->with('error', 'El titular del documento no existe.');
        }

        $author = auth()->user();

        // Foto del papel ANTES de persistir; ImageCompressor nunca pierde la evidencia.
        $photoPath = $request->hasFile('photo')
            ? ImageCompressor::store($request->file('photo'), 'ambulance/docs')
            : null;

        ExternalAuthorization::create([
            'holder_type'         => $holderClass,
            'holder_id'           => $holder->id,
            // El nivel se DERIVA del titular (no se confía en el cliente).
            'level'               => $data['holder_type'] === 'empresa'
                ? ExternalAuthorization::LEVEL_COMPANY
                : ExternalAuthorization::LEVEL_PERSON,
            'document_type'       => $data['document_type'],
            'authority'           => $data['authority'] ?? null,
            'folio'               => $data['folio'] ?? null,
            'valid_until'         => $data['valid_until'] ?? null,
            'photo_path'          => $photoPath,
            'origen'              => $data['origen'],
            'exigido_por'         => $data['exigido_por'] ?? null,
            'is_gate'             => $request->boolean('is_gate'),
            'status'              => $data['status'],
            'pending_commit_date' => $data['pending_commit_date'] ?? null,
            'standard_code'       => $data['standard_code'] ?? null,
            'standard_name'       => $data['standard_name'] ?? null,
            'is_active'           => 1,
            'created_by_id'       => $author ? $author->id : null,
        ]);

        return back()->with('success', 'Documento capturado. Queda pendiente de validar.');
    }

    /**
     * VALIDACIÓN MANUAL del documento (calca MedicCredentialController::verify). Exige la
     * declaración de cotejo: la validación se registra a nombre de quien la hace y lo hace
     * responsable. Hoy siempre 'documents_reviewed' (alguien vio el papel); NO hay consulta
     * a registro. Idempotente: no repisa un documento ya validado. Los campos validated_*
     * NO están en fillable → se escriben a mano.
     */
    public function validateDocument(Request $request, ExternalAuthorization $doc)
    {
        if (! $request->boolean('attestation')) {
            return back()->with('error', 'Para validar el documento tienes que marcar la declaración de cotejo: la validación se registra a tu nombre y te hace responsable de ella.');
        }

        // Idempotente: re-validar uno ya validado no es error ni repisa el rastro.
        if ($doc->isValidated()) {
            return back()->with('success', 'Este documento ya estaba validado.');
        }

        $actor = auth()->user();

        $doc->validated_at      = now();
        $doc->validated_by_id   = $actor ? $actor->id : null;
        $doc->validation_method = ExternalAuthorization::METHOD_DOCS; // hoy siempre documentos revisados
        $doc->validated_snapshot = [
            'attested'      => true,
            'validated_by'  => $actor ? $actor->fullName() : null,
            'attested_role' => $actor ? $actor->getRoleNames()->implode(', ') : null,
            'attested_ip'   => $request->ip(),
            'checked_at'    => now()->toDateTimeString(),
        ];
        $doc->save();

        return back()->with('success', 'Documento validado bajo tu responsabilidad (documentos revisados).');
    }

    /* ===================== VERIFICACIÓN (CHECKLIST) ===================== */

    /**
     * Formulario de verificación: elegir tipo + disparador (y capacidad para aérea/marítima).
     * Si ya viene ?type_id y ?trigger, carga los puntos aplicables FILTRADOS por el disparador.
     *   · 'riesgo'    → 0 puntos: se captura la correspondencia tipo↔riesgo (juicio del safety).
     *   · 'identidad' → confirmación de un toque (¿misma unidad y tripulación?); un "no" reenvía
     *                    a unidad/persona (asunto de la vista).
     *   · unidad/persona/consumo → sus puntos del catálogo.
     */
    public function inspectForm(Request $request)
    {
        $types = AmbulanceType::active()->orderBy('code')->get();

        $typeId        = $request->query('type_id');
        $capacityLevel = $request->query('capacity_level');
        $capacityLevel = ($capacityLevel !== null && $capacityLevel !== '') ? (int) $capacityLevel : null;

        $type   = null;
        $points = collect();

        // UN SOLO CHECKLIST: al elegir el tipo se carga COMPLETO (todos los puntos que le
        // tocan por rama+nivel). No hay disparador por evento; cada ambulancia en set se
        // verifica entera, como una herramienta.
        if ($typeId) {
            $type = AmbulanceType::active()->find($typeId);
            if ($type) {
                $points = $type->applicablePoints($capacityLevel);
            }
        }

        // Proveedores activos con su padrón, para elegir la empresa/tripulación (o dar de alta).
        $providers = AmbulanceProvider::active()->orderBy('name')
            ->with(['crew' => function ($q) {
                $q->where('is_active', 1)->orderBy('full_name');
            }])
            ->get();

        return view('ambulance.execute', compact('types', 'type', 'capacityLevel', 'points', 'providers'));
    }

    /**
     * Ejecuta la verificación y sella el acta. ESPEJO de InspectionController::store: los
     * puntos (is_gate/outcome_if_fail) se leen SIEMPRE del servidor, nunca del cliente; el
     * veredicto lo deriva AmbulanceVerdict::compute; la foto se guarda ANTES de sellar y el
     * acta se crea + sella de forma atómica.
     */
    public function storeInspection(Request $request)
    {
        $photoRule = 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288';
        $data = $request->validate([
            'type_id'              => 'required|integer|exists:ambulance_types,id',
            'capacity_level'       => 'nullable|integer|min:1|max:4',
            // Empresa: existente, o ALTA NUEVA en este mismo apartado.
            'provider_id'          => 'nullable|integer|exists:ambulance_providers,id',
            'new_provider_name'    => 'nullable|string|max:255',
            'plates'               => 'nullable|string|max:40',
            'economic_number'      => 'nullable|string|max:60',
            'unit_photo'           => $photoRule,                 // TODA foto del checklist es OPCIONAL
            'evidence_photos'      => 'nullable|array|max:20',     // evidencia adicional (varias) para sostener la decisión
            'evidence_photos.*'    => $photoRule,
            // Checklist COMPLETO (todos los puntos del tipo).
            'answers'              => 'nullable|array',
            'answers.*'            => 'in:ok,fail',
            // Tripulación: padrón existente + altas nuevas con cotejo CONOCER.
            'crew_ids'             => 'nullable|array',
            'crew_ids.*'           => 'integer|exists:ambulance_crew,id',
            'crew'                 => 'nullable|array',
            'crew.*.name'          => 'nullable|string|max:255',
            'crew.*.role'          => 'nullable|string|max:120',
            'crew.*.conocer_folio' => 'nullable|string|max:120',
            'crew.*.standard_code' => 'nullable|string|max:60',
            'crew.*.standard_name' => 'nullable|string|max:255',
            'crew.*.cert_photo'    => $photoRule,                 // sustento: foto del certificado CONOCER
            'crew.*.person_photo'  => $photoRule,                 // sustento: foto de la persona (no solo el papel)
            // Correspondencia tipo↔riesgo del día (opcional; criterio del safety).
            'day_risk_level'       => 'nullable|integer|min:1|max:5',
            'correspondence_ok'    => 'nullable|boolean',
            'note'                 => 'nullable|string|max:2000',
        ]);

        $capacityLevel = (isset($data['capacity_level']) && $data['capacity_level'] !== null)
            ? (int) $data['capacity_level'] : null;

        $type = AmbulanceType::active()->find($data['type_id']);
        if (! $type) {
            return back()->withInput()->with('error', 'El tipo de ambulancia no está disponible.');
        }

        $author = auth()->user();

        // PROVEEDOR (empresa): existente, o ALTA NUEVA en este mismo apartado. Cada ambulancia
        // se enlaza a su empresa; si es de la MISMA empresa se reusa, si es de otra se da de alta.
        $provider = null;
        if (! empty($data['provider_id'])) {
            $provider = AmbulanceProvider::find($data['provider_id']);
        } elseif (trim((string) ($data['new_provider_name'] ?? '')) !== '') {
            $provider = AmbulanceProvider::create([
                'name'          => trim($data['new_provider_name']),
                'created_by_id' => $author ? $author->id : null,
                'is_active'     => 1,
            ]);
        }
        if (! $provider) {
            return back()->withInput()->with('error', 'Elige la empresa proveedora o da una de alta: cada ambulancia se enlaza a su empresa.');
        }

        // CHECKLIST COMPLETO del tipo (autoritativo del servidor: is_gate/outcome nunca del
        // cliente). Un solo checklist, se corre entero como con herramienta/maquinaria.
        $points = $type->applicablePoints($capacityLevel);
        if ($points->isEmpty()) {
            return back()->withInput()->with('error', 'Este tipo de ambulancia no tiene puntos de verificación.');
        }

        // Cruzar respuestas contra los puntos reales; armar la lista ejecutada + snapshot.
        $answers  = $data['answers'] ?? [];
        $executed = [];
        $snapshot = [];
        foreach ($points as $p) {
            $code = $p->code;
            $raw  = $answers[$code] ?? null;
            $ans  = $raw === 'fail' ? false : ($raw === 'ok' ? true : null);
            if ($ans === null) {
                return back()->withInput()->with('error', "Falta responder el punto {$code}. Una verificación incompleta no se guarda.");
            }
            $executed[] = [
                'is_gate'         => (bool) $p->is_gate,
                'outcome_if_fail' => $p->outcome_if_fail,
                'answer'          => $ans,
            ];
            $snapshot[] = [
                'code'              => $code,
                'text'             => $p->text_es,
                'is_gate'          => (bool) $p->is_gate,
                'outcome_if_fail'  => $p->outcome_if_fail,
                'norm'             => $p->norm_ref ?? ($p->norm_code ?? null),
                'requires_document' => (bool) $p->requires_document,
                'answer'           => $ans ? 'ok' : 'fail',
            ];
        }

        $result = AmbulanceVerdict::compute($executed);

        // Correspondencia tipo↔riesgo del día (OPCIONAL; la fija el criterio del safety, no el
        // catálogo). Solo cuenta si se declaró el nivel de riesgo del día. Vive en su PROPIO campo
        // (correspondence_ok) y se muestra en la sección de datos del acta, no en observaciones.
        $dayRisk          = isset($data['day_risk_level']) ? (int) $data['day_risk_level'] : null;
        $correspondenceOk = null;
        if ($dayRisk !== null && $request->has('correspondence_ok')) {
            $correspondenceOk = $request->boolean('correspondence_ok');
        }

        // Observaciones = SOLO lo que el responsable escriba en el campo. NO se auto-rellena con las
        // fallas del checklist (ya salen como FALLA en su tabla) ni con la correspondencia (vive en
        // su propio campo): duplicarlo no dice nada nuevo y ensucia el acta.
        $observations = ! empty($data['note']) ? trim($data['note']) : null;

        // TRIPULACIÓN → snapshot CONGELADO. El padrón por id (checkbox) trae su cotejo CONOCER
        // ya guardado; las altas nuevas se crean bajo la empresa y su TAMP se COTEJA como
        // verificado cuando trae folio CONOCER + foto del certificado + foto de la persona
        // (sustento fotográfico, no solo del papel). Sin ese sustento queda registrado sin cotejar.
        $crewSnapshot = [];
        foreach ((array) ($data['crew_ids'] ?? []) as $cid) {
            $member = AmbulanceCrew::with('authorizations')->find($cid);
            if (! $member) {
                continue;
            }
            $conocer = $member->authorizations->firstWhere('document_type', 'CONOCER');
            $crewSnapshot[] = [
                'name'          => $member->full_name,
                'role'          => $member->crew_role,
                'crew_id'       => $member->id,
                'conocer_folio' => $conocer ? $conocer->folio : null,
                'verified'      => (bool) ($conocer && $conocer->isValidated()),
            ];
        }
        foreach (($data['crew'] ?? []) as $i => $member) {
            $name = trim((string) ($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $role = trim((string) ($member['role'] ?? '')) ?: null;

            // Foto de la PERSONA (cara) → id_photo del padrón; opcional.
            $personPhoto = $request->hasFile("crew.$i.person_photo")
                ? ImageCompressor::store($request->file("crew.$i.person_photo"), 'ambulance/crew')
                : null;
            $crewMember = AmbulanceCrew::create([
                'provider_id'   => $provider->id,
                'full_name'     => $name,
                'crew_role'     => $role,
                'id_photo_path' => $personPhoto,
                'created_by_id' => $author ? $author->id : null,
                'is_active'     => 1,
            ]);

            $folio     = trim((string) ($member['conocer_folio'] ?? ''));
            $certPhoto = $request->hasFile("crew.$i.cert_photo")
                ? ImageCompressor::store($request->file("crew.$i.cert_photo"), 'ambulance/docs')
                : null;
            $verified  = false;
            if ($folio !== '') {
                $doc = ExternalAuthorization::create([
                    'holder_type'   => AmbulanceCrew::class,
                    'holder_id'     => $crewMember->id,
                    'level'         => ExternalAuthorization::LEVEL_PERSON,
                    'document_type' => 'CONOCER',
                    'authority'     => 'CONOCER',
                    'folio'         => $folio,
                    'photo_path'    => $certPhoto,
                    'standard_code' => trim((string) ($member['standard_code'] ?? '')) ?: null,
                    'standard_name' => trim((string) ($member['standard_name'] ?? '')) ?: null,
                    'origen'        => 'normativo',
                    'is_gate'       => 0,
                    'status'        => ExternalAuthorization::STATUS_PRESENTED,
                    'created_by_id' => $author ? $author->id : null,
                    'is_active'     => 1,
                ]);
                // COTEJO = folio + certificado + persona. Solo entonces se da por VERIFICADO.
                if ($certPhoto && $personPhoto) {
                    $doc->validation_method  = ExternalAuthorization::METHOD_DOCS;
                    $doc->validated_at       = now();
                    $doc->validated_by_id    = $author ? $author->id : null;
                    $doc->validated_snapshot = [
                        'attested'      => true,
                        'validated_by'  => $author ? $author->fullName() : null,
                        'attested_role' => $author ? optional($author->getRoleNames())->first() : null,
                        'attested_ip'   => $request->ip(),
                        'checked_at'    => now()->toDateTimeString(),
                        'basis'         => 'folio CONOCER + foto del certificado + foto de la persona',
                    ];
                    $doc->save();
                    $verified = true;
                }
            }

            $crewSnapshot[] = [
                'name'          => $name,
                'role'          => $role,
                'crew_id'       => $crewMember->id,
                'conocer_folio' => $folio ?: null,
                'verified'      => $verified,
            ];
        }

        // Inspector (doctrina de congelamiento) + cédula si el módulo está disponible.
        $cred = ($author && MedicCredential::supportsCredentials()) ? $author->medicCredential : null;

        // Foto REAL de la unidad ANTES de sellar (su RUTA entra en el hash).
        $photoPath = $request->hasFile('unit_photo')
            ? ImageCompressor::store($request->file('unit_photo'), 'ambulance/units')
            : null;

        // Evidencia fotográfica adicional (varias, opcional): pruebas para sostener la
        // decisión —revocar (paro) o autorizar (apta)— después del hecho. Sus rutas entran
        // al sello (evidencia inmutable; cambiar la lista de un acta sellada = ALTERADO).
        $evidencePaths = [];
        foreach ((array) $request->file('evidence_photos', []) as $file) {
            if (! $file) {
                continue;
            }
            $stored = ImageCompressor::store($file, 'ambulance/evidence');
            if ($stored) {
                $evidencePaths[] = $stored;
            }
        }

        $payload = [
            'production_id'      => CurrentProduction::id(),
            'shoot_day'          => $this->currentShootDay(),
            'trigger_scope'      => AmbulanceInspection::TRIGGER_FULL,
            'ambulance_type_id'  => $type->id,
            'type_code'          => $type->code,
            'type_name'          => $type->name_es,
            'rama'               => $type->rama,
            'type_level'         => $type->level,
            // La capacidad resolutiva solo aplica a aérea/marítima; la terrestre la deriva del tipo.
            'capacity_level'     => $type->isTerrestre() ? null : $capacityLevel,
            'provider_id'        => $provider->id,
            'provider_name'      => $provider->name,
            'plates'             => $data['plates'] ?? null,
            'economic_number'    => $data['economic_number'] ?? null,
            'unit_photo_path'    => $photoPath,
            'evidence_photos'    => $evidencePaths ?: null,
            'crew_snapshot'      => $crewSnapshot,
            'checklist_snapshot' => $snapshot,
            'verdict'            => $result['verdict'],
            'resolution_path'    => $result['resolution_path'], // null: no hay vía de salida
            'observations'       => $observations,
            'day_risk_level'     => $dayRisk,
            'correspondence_ok'  => $correspondenceOk,
            'inspector_user_id'  => $author ? $author->id : null,
            // Nombre del que firma: mismo criterio que el resto de documentos (DSR/Injury/Scouting
            // usan ->name), no el nombre completo con apellidos → "Ari Rómulo", no "Ari Rómulo Romulo".
            'inspector_name'     => $author ? $author->name : null,
            'inspector_role'     => $author ? optional($author->getRoleNames())->first() : null,
            'inspector_cedula'   => $cred ? $cred->cedula : null,
            'is_active'          => 1,
        ];

        // Crear y SELLAR de forma ATÓMICA: si el sellado fallara, no queda un acta a
        // medias. refresh() antes de firmar → hashea los valores canónicos de BD.
        $inspection = DB::transaction(function () use ($payload, $author, $request) {
            $insp = AmbulanceInspection::create($payload);
            $insp->refresh();
            $insp->signDocument($author, $request);
            return $insp;
        });

        // PARO ⇒ genera la obligación (action item con el flujo PDCA existente), fin del día.
        if ($inspection->isParo()) {
            $this->raiseActionItem($inspection, $author);
        }

        return redirect()->route('ambulance.acta', $inspection->uuid)
            ->with('success', 'Verificación registrada y sellada.');
    }

    /* ===================== EL ACTA (interna, sellada) ===================== */

    public function actaShow(AmbulanceInspection $inspection)
    {
        $inspection->load('supersededBy');
        $actionItem = Schema::hasTable('action_items')
            ? $inspection->actionItems()->where('source_field', 'ambulance_paro')->latest('id')->first()
            : null;

        return view('ambulance.acta', compact('inspection', 'actionItem'));
    }

    /* ===================== DESBLOQUEO DEL PARO ===================== */

    public function unblock(Request $request, AmbulanceInspection $inspection)
    {
        if (! $inspection->isParo()) {
            return back()->with('error', 'Solo un PARO se desbloquea.');
        }
        if ($inspection->unblocked_at !== null) {
            return back()->with('error', 'Este paro ya fue desbloqueado.');
        }

        // Estado + re-sellado viven en el modelo (los unblocked_* SÍ entran al hash).
        $inspection->unblock(auth()->user(), $request);

        return redirect()->route('ambulance.acta', $inspection->uuid)
            ->with('success', 'Paro desbloqueado y re-sellado.');
    }

    /* ===================== CONSULTA · ACTAS ===================== */

    /** Histórico de actas para consultar. Busca por placas, número económico, proveedor, tipo o folio. */
    public function records(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = AmbulanceInspection::query();

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('plates', 'like', $like)
                  ->orWhere('economic_number', 'like', $like)
                  ->orWhere('provider_name', 'like', $like)
                  ->orWhere('type_name', 'like', $like)
                  ->orWhere('type_code', 'like', $like);
            });
            if (preg_match('/(\d+)/', $q, $m)) {
                $query->orWhere('id', (int) $m[1]); // folio AMBU-000N por número
            }
        }

        $inspections = $query->orderBy('created_at', 'desc')->paginate(30)->withQueryString();

        return view('ambulance.records', compact('inspections', 'q'));
    }

    /* ============================ Helpers ============================ */

    /** El shoot day de hoy (blindado: nunca rompe el guardado). */
    private function currentShootDay()
    {
        try {
            return ProductionCalendar::shootDayFor(now()->toDateString());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** El acta VIGENTE del día (production+shoot_day), o null si no hay o está bloqueada/retirada. */
    private function vigenteInspectionForDay($productionId, $shootDay): ?AmbulanceInspection
    {
        if (! $productionId || $shootDay === null) {
            return null;
        }
        $acta = AmbulanceInspection::where('production_id', $productionId)
            ->where('shoot_day', $shootDay)
            ->latest('id')
            ->first();

        return ($acta && $acta->isVigente()) ? $acta : null;
    }

    /** Crea el action item de la obligación (PDCA existente), ligado al acta, con plazo fin del día. */
    private function raiseActionItem(AmbulanceInspection $inspection, $author): void
    {
        if (! Schema::hasTable('action_items')) {
            return;
        }
        $due  = now()->endOfDay();
        $text = "PARO de recurso de emergencia ({$inspection->type_code} {$inspection->type_name}): "
              . "la unidad no puede operar hasta corregir. Folio {$inspection->folio()}.";
        $inspection->syncAutoActionItem($text, 'ambulance_paro', $author ? $author->id : null, $due);
    }
}
