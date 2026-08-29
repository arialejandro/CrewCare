<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\MedicCredential;
use App\Models\Payee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleInspectionDraft;
use App\Models\VehicleType;
use App\Support\CurrentProduction;
use App\Support\ImageCompressor;
use App\Support\ProductionCalendar;
use App\Support\TransportAccess;
use App\Support\TransportDrivers;
use App\Support\VehicleChecklist;
use App\Support\VehicleVerdict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRANSPORTACIÓN · Bloque 1 — entidad Vehículo + verificación de seguridad.
 *
 * Hermana de {@see AmbulanceController}: catálogo → checklist → veredicto DERIVADO (fail-safe) →
 * acta sellada + verificador público 'veh'. Diferencias del bloque: veredicto GRADUADO
 * ({@see VehicleVerdict}), atributos que encienden módulos del checklist ({@see VehicleChecklist}),
 * fotos OBLIGATORIAS por punto, y reevaluación encadenada.
 *
 * AUTORIZACIÓN (decisión del owner, HÍBRIDO): las rutas van solo con `auth`; el acceso se compone
 * en {@see TransportAccess} — canFull (transport.manage O pertenencia al depto de Transportación)
 * para gestionar; canLite (transport.view O canFull) para la vista de producción. El acta es
 * crear-y-sellar INMUTABLE: nadie la edita, así que "el depto no edita actas" queda satisfecho.
 */
class VehicleController extends Controller
{
    /* ============================ HUB ============================ */

    public function index(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $vehicles = Vehicle::active()
            ->with(['type', 'driver', 'ownerPayee', 'ownerUser', 'documents.documentType', 'inspections'])
            ->orderBy('make')->orderBy('model')
            ->get();

        $recent = VehicleInspection::query()
            ->orderBy('created_at', 'desc')->limit(10)->get();

        return view('vehicle.index', compact('vehicles', 'recent'));
    }

    /* ======================= FLOTA / VEHÍCULOS ======================= */

    /** Padrón de vehículos + formulario de alta. */
    public function vehicles(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $vehicles = Vehicle::active()
            ->with(['type', 'driver', 'ownerPayee', 'ownerUser'])
            ->orderBy('make')->orderBy('model')->get();

        [$types, $crew, $payees] = $this->pickers();
        $drivers = TransportDrivers::forVehicleForm(CurrentProduction::id(), null);

        return view('vehicle.vehicles', compact('vehicles', 'types', 'crew', 'payees', 'drivers'));
    }

    /** Alta de vehículo. El tipo PROPONE el perfil; los atributos se guardan ya resueltos/ajustados. */
    public function storeVehicle(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $this->validateVehicle($request);

        $author = $request->user();
        $type   = ! empty($data['vehicle_type_id']) ? VehicleType::find($data['vehicle_type_id']) : null;

        // Un driver, una unidad (FILTRO, no candado): si ya conducía otra, la libera y AVISA.
        $freed = $this->freePreviousDriver($data['driver_user_id'] ?? null, null);

        $vehicle = Vehicle::create($this->vehiclePayload($request, $data, $type) + [
            'created_by_id' => $author ? $author->id : null,
            'is_active'     => 1,
        ]);

        return redirect()->route('transport.vehicle.show', $vehicle)
            ->with('success', 'Vehículo registrado.')->with('warn', $freed);
    }

    /** Ficha del vehículo: atributos, documentos (con vigencia), actas y su cadena. */
    public function vehicleShow(Request $request, Vehicle $vehicle)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $vehicle->load(['type', 'driver', 'ownerPayee', 'ownerUser', 'documents.documentType']);
        $inspections   = $vehicle->inspections()->get();
        $docState      = $vehicle->requiredDocState();
        $docTypes      = DocumentType::whereIn('code', Vehicle::REQUIRED_DOC_CODES)->orderBy('sort_order')->get();
        $driverLicense = $vehicle->driverLicense(); // §2: vive en el paquete del driver, aquí se MUESTRA

        return view('vehicle.vehicle-show', compact('vehicle', 'inspections', 'docState', 'docTypes', 'driverLicense'));
    }

    /** Formulario de edición del vehículo (atributos ajustables por unidad, driver, propietario). */
    public function editVehicle(Request $request, Vehicle $vehicle)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        [$types, $crew, $payees] = $this->pickers();
        $attrs = $vehicle->resolvedAttributes();
        $drivers = TransportDrivers::forVehicleForm(CurrentProduction::id(), $vehicle->id);

        return view('vehicle.vehicle-edit', compact('vehicle', 'types', 'crew', 'payees', 'attrs', 'drivers'));
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $this->validateVehicle($request);
        $type = ! empty($data['vehicle_type_id']) ? VehicleType::find($data['vehicle_type_id']) : null;

        // Un driver, una unidad (FILTRO, no candado): si ya conducía otra, la libera y AVISA. No la libera solo.
        $freed = $this->freePreviousDriver($data['driver_user_id'] ?? null, $vehicle->id);

        $vehicle->update($this->vehiclePayload($request, $data, $type));

        return redirect()->route('transport.vehicle.show', $vehicle)
            ->with('success', 'Vehículo actualizado.')->with('warn', $freed);
    }

    /**
     * Un DRIVER en una sola unidad: si $driverId ya conducía OTRA unidad activa, la deja SIN conductor
     * y devuelve el aviso (para no liberarla en silencio). Reasignar es deliberado y con la info a la
     * vista; el traslape a nivel corrida lo cubre la Fase 5, esto es la asignación misma.
     */
    private function freePreviousDriver(?int $driverId, ?int $exceptVehicleId): ?string
    {
        $other = TransportDrivers::otherVehicleOf($driverId ? (int) $driverId : null, $exceptVehicleId);
        if (! $other) {
            return null;
        }
        $label = TransportDrivers::vehLabel($other);
        $other->driver_user_id = null;
        $other->save();

        return __(':veh quedó sin conductor: moviste a ese chofer a esta unidad.', ['veh' => $label]);
    }

    /* ===================== DOCUMENTOS (§4) ===================== */

    /**
     * Captura un documento del vehículo (holder = Vehicle). NACE PENDIENTE: los campos validated_*
     * NO se tocan aquí (capturar ≠ cotejar). Se captura FECHA DE VENCIMIENTO explícita.
     */
    public function storeDocument(Request $request, Vehicle $vehicle)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $request->validate([
            'document_type_id' => 'required|integer|exists:document_types,id',
            'authority'        => 'nullable|string|max:255',
            'folio'            => 'nullable|string|max:160',
            'valid_until'      => 'nullable|date',
            'photo'            => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
        ]);

        $type = DocumentType::find($data['document_type_id']);
        if (! $type || ! in_array($type->code, Vehicle::REQUIRED_DOC_CODES, true)) {
            return back()->withInput()->with('error', 'Tipo de documento no válido para un vehículo.');
        }

        $author    = $request->user();
        $photoPath = $request->hasFile('photo')
            ? ImageCompressor::store($request->file('photo'), 'vehicle/docs')
            : null;

        ExternalAuthorization::create([
            'holder_type'      => Vehicle::class,
            'holder_id'        => $vehicle->id,
            'level'            => ExternalAuthorization::LEVEL_COMPANY, // rótulo; el doc es del vehículo
            'document_type'    => $type->name,
            'document_type_id' => $type->id,
            'authority'        => $data['authority'] ?? null,
            'folio'            => $data['folio'] ?? null,
            'valid_until'      => $data['valid_until'] ?? null,
            'photo_path'       => $photoPath,
            'origen'           => 'normativo',
            'is_gate'          => 0,
            'status'           => ExternalAuthorization::STATUS_PRESENTED,
            'is_active'        => 1,
            'created_by_id'    => $author ? $author->id : null,
        ]);

        return back()->with('success', 'Documento capturado. Queda pendiente de validar.');
    }

    /** VALIDACIÓN MANUAL del documento (calca AmbulanceController::validateDocument). */
    public function validateDocument(Request $request, ExternalAuthorization $doc)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        if (! $request->boolean('attestation')) {
            return back()->with('error', 'Para validar tienes que marcar la declaración de cotejo: la validación se registra a tu nombre y te hace responsable.');
        }
        if ($doc->isValidated()) {
            return back()->with('success', 'Este documento ya estaba validado.');
        }

        $actor = $request->user();
        $doc->validated_at       = now();
        $doc->validated_by_id    = $actor ? $actor->id : null;
        $doc->validation_method  = ExternalAuthorization::METHOD_DOCS;
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

    /**
     * LICENCIA DEL CONDUCTOR (§2): NO es del vehículo. Vive en el paquete documental del DRIVER
     * (payee 1:1 por user_id), junto a su 32-D/CSF, para consulta de contabilidad. Se captura UNA
     * vez por conductor; si maneja dos unidades, la licencia sigue siendo una sola. Nace pendiente.
     */
    public function storeDriverLicense(Request $request, Vehicle $vehicle)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        if (! $vehicle->driver_user_id) {
            return back()->with('error', 'Asigna un conductor al vehículo antes de capturar su licencia.');
        }
        $driver = User::find($vehicle->driver_user_id);
        if (! $driver) {
            return back()->with('error', 'El conductor asignado no existe.');
        }

        $data = $request->validate([
            'authority'   => 'nullable|string|max:255',
            'folio'       => 'nullable|string|max:160',
            'valid_until' => 'nullable|date',
            'photo'       => 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288',
        ]);

        $type = DocumentType::where('code', 'VEH_LICENCIA')->first();
        if (! $type) {
            return back()->with('error', 'El tipo de documento de licencia no está disponible.');
        }

        // La licencia va al PAQUETE del driver. Se asegura su payee (idempotente, 1:1 por user_id).
        $payee = Payee::firstOrCreate(
            ['user_id' => $driver->id],
            [
                'legal_nature' => Payee::NATURE_FISICA,
                'name'         => trim(($driver->name ?? '') . ' ' . ($driver->lname ?? '')) ?: ($driver->name ?? 'Conductor'),
                'is_active'    => 1,
            ]
        );

        $author    = $request->user();
        $photoPath = $request->hasFile('photo')
            ? ImageCompressor::store($request->file('photo'), 'vehicle/licenses')
            : null;

        ExternalAuthorization::create([
            'holder_type'      => Payee::class,
            'holder_id'        => $payee->id,
            'level'            => ExternalAuthorization::LEVEL_PERSON, // 'persona' → sección "Para cobrar" del payee
            'document_type'    => $type->name,
            'document_type_id' => $type->id,
            'authority'        => $data['authority'] ?? null,
            'folio'            => $data['folio'] ?? null,
            'valid_until'      => $data['valid_until'] ?? null,
            'photo_path'       => $photoPath,
            'origen'           => 'normativo',
            'is_gate'          => 0,
            'status'           => ExternalAuthorization::STATUS_PRESENTED,
            'is_active'        => 1,
            'created_by_id'    => $author ? $author->id : null,
        ]);

        return back()->with('success', 'Licencia capturada en el paquete del conductor. Queda pendiente de validar.');
    }

    /* ===================== VERIFICACIÓN (CHECKLIST) ===================== */

    /**
     * Formulario de verificación de un vehículo: carga los puntos que le TOCAN por sus atributos
     * (applies_when). Si `?reeval=<id>` viene, es reevaluación de esa acta: se marca `reparado` por
     * punto y se saltan los documentos ya validados y vigentes.
     */
    public function inspectForm(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $vehicleId = $request->query('vehicle_id');
        $vehicle   = $vehicleId ? Vehicle::with(['type', 'driver'])->find($vehicleId) : null;

        $points = collect();
        $attrs  = [];
        if ($vehicle) {
            $attrs  = $vehicle->resolvedAttributes();
            $points = VehicleChecklist::pointsFor($attrs);
        }

        // Reevaluación: acta de origen (para marcar reparado y saltar docs vigentes).
        $origin = null;
        if ($request->filled('reeval')) {
            $origin = VehicleInspection::find($request->query('reeval'));
            if ($origin && ! $vehicle) {
                $vehicle = Vehicle::with(['type', 'driver'])->find($origin->vehicle_id);
                if ($vehicle) {
                    $attrs  = $vehicle->resolvedAttributes();
                    $points = VehicleChecklist::pointsFor($attrs);
                }
            }
        }

        // BORRADOR del autor para este vehículo (§1): retoma respuestas/fotos guardadas. Si el
        // borrador es de una reevaluación, recupera su acta de origen.
        $draft = $vehicle ? VehicleInspectionDraft::forAuthor($vehicle->id, optional($request->user())->id) : null;
        if ($draft && $draft->is_reevaluation && $draft->origin_inspection_id && ! $origin) {
            $origin = VehicleInspection::find($draft->origin_inspection_id);
        }

        $vehicles = Vehicle::active()->with('type')->orderBy('make')->orderBy('model')->get();
        $docState = $vehicle ? $vehicle->requiredDocState() : [];

        return view('vehicle.execute', compact('vehicles', 'vehicle', 'points', 'attrs', 'origin', 'docState', 'draft'));
    }

    /**
     * BORRADOR (§1): guardado parcial EN SERVIDOR. NO es un acta ni sella nada. Del AUTOR: un
     * borrador vivo por vehículo+autor. Las respuestas contestadas se MERGEAN (una nula no borra);
     * cada foto nueva se guarda al vuelo y su ruta se recuerda (no se re-sube al retomar/sellar).
     */
    public function saveDraft(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $photoRule = 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288';
        $data = $request->validate([
            'vehicle_id'     => 'required|integer|exists:vehicles,id',
            'answers'        => 'nullable|array',
            'answers.*'      => 'nullable|in:ok,fail',
            'point_photos'   => 'nullable|array',
            'point_photos.*' => $photoRule,
            'km'             => 'nullable|integer|min:0|max:9999999',
            'unit_photo'     => $photoRule,
            'observations'   => 'nullable|string|max:4000',
            'reeval'         => 'nullable|integer|exists:vehicle_inspections,id',
        ]);

        $vehicle = Vehicle::find($data['vehicle_id']);
        if (! $vehicle) {
            return back()->with('error', 'El vehículo no está disponible.');
        }
        $author = $request->user();

        $draft = VehicleInspectionDraft::forAuthor($vehicle->id, $author->id)
            ?? new VehicleInspectionDraft(['vehicle_id' => $vehicle->id, 'created_by_id' => $author->id]);

        // Respuestas: MERGE (solo las contestadas; una nula no borra lo guardado).
        $answers = (array) ($draft->answers ?? []);
        foreach (($data['answers'] ?? []) as $code => $val) {
            if ($val === 'ok' || $val === 'fail') {
                $answers[$code] = $val;
            }
        }

        // Fotos por punto: cada archivo NUEVO se guarda al vuelo; su ruta se recuerda.
        $photos = (array) ($draft->point_photos ?? []);
        foreach ((array) $request->file('point_photos', []) as $code => $file) {
            if ($file) {
                $stored = ImageCompressor::store($file, 'vehicle/points');
                if ($stored) {
                    $photos[$code] = $stored;
                }
            }
        }

        $unitPhoto = $request->hasFile('unit_photo')
            ? ImageCompressor::store($request->file('unit_photo'), 'vehicle/units')
            : $draft->unit_photo_path;

        $origin = $request->filled('reeval') ? VehicleInspection::find($data['reeval']) : null;

        $draft->fill([
            'production_id'        => CurrentProduction::id(),
            'is_reevaluation'      => $origin ? 1 : 0,
            'origin_inspection_id' => $origin ? $origin->id : null,
            'answers'              => $answers,
            'point_photos'         => $photos,
            'unit_photo_path'      => $unitPhoto,
            'km'                   => $request->filled('km') ? (int) $data['km'] : $draft->km,
            'observations'         => $data['observations'] ?? $draft->observations,
        ]);
        $draft->save();

        $params = ['vehicle_id' => $vehicle->id] + ($origin ? ['reeval' => $origin->id] : []);
        return redirect()->route('transport.inspect.form', $params)
            ->with('success', 'Borrador guardado. Puedes retomarlo desde cualquier dispositivo.');
    }

    /** Descarta el borrador del autor para un vehículo. */
    public function discardDraft(Request $request, Vehicle $vehicle)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);
        $draft = VehicleInspectionDraft::forAuthor($vehicle->id, optional($request->user())->id);
        if ($draft) {
            $draft->delete();
        }
        return redirect()->route('transport.vehicle.show', $vehicle)->with('success', 'Borrador descartado.');
    }

    /**
     * Ejecuta la verificación y sella el acta. Fail-safe DOBLE: (1) punto aplicable sin contestar
     * aborta el guardado; (2) foto obligatoria (requires_photo o punto reprobado) faltante aborta.
     * Los puntos y su clase se leen SIEMPRE del servidor, nunca del cliente. Veredicto graduado.
     * Si hay borrador del autor, sus fotos ya guardadas sirven de respaldo; al sellar se BORRA.
     */
    public function storeInspection(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $photoRule = 'nullable|mimes:jpeg,png,jpg,gif,webp,heic,heif|heic_ok|max:12288';
        $data = $request->validate([
            'vehicle_id'     => 'required|integer|exists:vehicles,id',
            'answers'        => 'nullable|array',
            'answers.*'      => 'in:ok,fail',
            'point_photos'   => 'nullable|array',
            'point_photos.*' => $photoRule,
            'reparado'       => 'nullable|array',
            'reparado.*'     => 'nullable|boolean',
            'km'             => 'nullable|integer|min:0|max:9999999',
            'unit_photo'     => $photoRule,
            'observations'   => 'nullable|string|max:4000',
            'reeval'         => 'nullable|integer|exists:vehicle_inspections,id',
        ]);

        $vehicle = Vehicle::with('type')->find($data['vehicle_id']);
        if (! $vehicle) {
            return back()->withInput()->with('error', 'El vehículo no está disponible.');
        }

        $author = $request->user();
        $attrs  = $vehicle->resolvedAttributes();
        $points = VehicleChecklist::pointsFor($attrs);
        if ($points->isEmpty()) {
            return back()->withInput()->with('error', 'Este vehículo no tiene puntos de verificación aplicables.');
        }

        // Borrador del autor (§1): sus fotos ya guardadas sirven de RESPALDO si no se re-subió el
        // archivo en el envío final (se retomó desde otro dispositivo). Al sellar, el borrador se borra.
        $draft       = VehicleInspectionDraft::forAuthor($vehicle->id, optional($author)->id);
        $draftPhotos = $draft ? (array) $draft->point_photos : [];

        $origin  = $request->filled('reeval') ? VehicleInspection::find($data['reeval']) : null;
        $isReeval = $origin !== null;

        $answers   = $data['answers'] ?? [];
        $reparado  = $data['reparado'] ?? [];
        $photos    = (array) $request->file('point_photos', []);
        $executed  = [];
        $snapshot  = [];

        foreach ($points as $p) {
            $code = $p->code;
            $raw  = $answers[$code] ?? null;
            $ans  = $raw === 'fail' ? false : ($raw === 'ok' ? true : null);

            // FAIL-SAFE 1: punto aplicable sin contestar → el acta NO cierra.
            if ($ans === null) {
                return back()->withInput()->with('error', "Falta responder el punto {$code}. Una verificación incompleta no se guarda.");
            }

            // FAIL-SAFE 2 (foto obligatoria): del catálogo (requires_photo) o TODO punto reprobado.
            $needsPhoto = $p->requires_photo || $ans === false;
            $photoPath  = null;
            if (isset($photos[$code]) && $photos[$code]) {
                $photoPath = ImageCompressor::store($photos[$code], 'vehicle/points');
            } elseif (! empty($draftPhotos[$code])) {
                $photoPath = $draftPhotos[$code]; // respaldo: foto ya guardada en el borrador
            }
            if ($needsPhoto && ! $photoPath) {
                $why = $ans === false ? 'un punto reprobado exige foto' : 'este punto exige foto';
                return back()->withInput()->with('error', "Falta la foto del punto {$code}: {$why}.");
            }

            $executed[] = ['class' => $p->class, 'answer' => $ans];
            $row = [
                'code'     => $code,
                'grupo'    => $p->grupo,
                'module'   => $p->module,
                'text'     => $p->text_es,
                'class'    => $p->class,
                'answer'   => $ans ? 'ok' : 'fail',
                'photo_path' => $photoPath,
            ];
            if ($isReeval) {
                $row['reparado'] = ! empty($reparado[$code]);
            }
            $snapshot[] = $row;
        }

        $result = VehicleVerdict::compute($executed);

        // Foto general de la unidad (opcional) ANTES de sellar (su ruta entra al hash). Respaldo del borrador.
        $unitPhoto = $request->hasFile('unit_photo')
            ? ImageCompressor::store($request->file('unit_photo'), 'vehicle/units')
            : ($draft ? $draft->unit_photo_path : null);

        // KM: se captura en la PRIMERA inspección; en reevaluación se conserva el del vehículo.
        $km = $data['km'] ?? $vehicle->initial_km;

        $cred = ($author && MedicCredential::supportsCredentials()) ? $author->medicCredential : null;

        $payload = [
            'production_id'        => CurrentProduction::id(),
            'shoot_day'            => $this->currentShootDay(),
            'vehicle_id'           => $vehicle->id,
            'vehicle_type_id'      => $vehicle->vehicle_type_id,
            'type_code'            => $vehicle->type_code ?? optional($vehicle->type)->code,
            'type_name'            => optional($vehicle->type)->name_es,
            'make'                 => $vehicle->make,
            'model'                => $vehicle->model,
            'year'                 => $vehicle->year,
            'color'                => $vehicle->color,
            'plate'                => $vehicle->plate,
            'vin'                  => $vehicle->vin,
            'attributes_snapshot'  => $attrs,
            'owner_kind'           => $vehicle->owner_kind,
            'owner_name'           => $vehicle->ownerLabel(),
            'driver_user_id'       => $vehicle->driver_user_id,
            'driver_name'          => $vehicle->driverLabel(),
            'km'                    => $km !== null ? (int) $km : null,
            'unit_photo_path'      => $unitPhoto,
            'checklist_snapshot'   => $snapshot,
            'verdict'              => $result['verdict'],
            'level'                => $result['level'],
            'n_critical'           => $result['n_critical'],
            'n_major'              => $result['n_major'],
            'n_minor'              => $result['n_minor'],
            'observations'         => ! empty($data['observations']) ? trim($data['observations']) : null,
            'is_reevaluation'      => $isReeval ? 1 : 0,
            'origin_inspection_id' => $origin ? $origin->id : null,
            'inspector_user_id'    => $author ? $author->id : null,
            'inspector_name'       => $author ? User::displayName($author) : null,
            'inspector_role'       => $author ? optional($author->getRoleNames())->first() : null,
            'inspector_cedula'     => $cred ? $cred->cedula : null,
            'created_by_id'        => $author ? $author->id : null,
            'is_active'            => 1,
        ];

        // Crear + SELLAR atómico. refresh() antes de firmar → hashea los valores canónicos de BD.
        $inspection = DB::transaction(function () use ($payload, $author, $request, $vehicle) {
            $insp = VehicleInspection::create($payload);
            $insp->refresh();
            $insp->signDocument($author, $request);

            // Una sola acta ACTIVA por vehículo: la anterior queda retirada + apunta a la nueva.
            // superseded_by_id/retired_* van HASH-EXCLUIDOS → no re-sellan el acta vieja.
            $prev = VehicleInspection::where('vehicle_id', $vehicle->id)
                ->where('id', '!=', $insp->id)
                ->where('is_active', 1)
                ->get();
            foreach ($prev as $old) {
                $old->is_active        = false;
                $old->retired_at       = now();
                $old->retired_by_id    = $author ? $author->id : null;
                $old->retired_reason   = 'Sustituida por nueva verificación';
                $old->superseded_by_id = $insp->id;
                $old->save();
            }
            return $insp;
        });

        // Sellada el acta, el borrador ya no sirve: se borra (el acta es la fuente inmutable).
        optional($draft)->delete();

        return redirect()->route('transport.acta', $inspection->uuid)
            ->with('success', 'Verificación registrada y sellada.');
    }

    /* ===================== EL ACTA (interna, sellada) ===================== */

    public function actaShow(Request $request, VehicleInspection $inspection)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $inspection->load('supersededBy', 'originInspection');

        if ($request->boolean('pdf')) {
            $html = view('vehicle.acta', compact('inspection'))->render();
            return \App\Support\PdfExporter::download($html, 'VEHI-' . substr((string) $inspection->uuid, 0, 8), [0, 0, 0, 0]);
        }

        return view('vehicle.acta', compact('inspection')
            + ['pdfUrl' => $request->fullUrlWithQuery(['pdf' => 1])]);
    }

    /**
     * PDF DE RECHAZO (§7): solo los puntos reprobados, su clase, foto y nivel. Visible solo para
     * safety/transpo (misma puerta canFull). Se genera solo si el acta es NO APTO.
     */
    public function rejectionPdf(Request $request, VehicleInspection $inspection)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);
        abort_unless($inspection->isNoApto(), 404);

        $html = view('vehicle.rejection', compact('inspection'))->render();
        return \App\Support\PdfExporter::download($html, 'VEHI-RECHAZO-' . substr((string) $inspection->uuid, 0, 8), [0, 0, 0, 0]);
    }

    /* ===================== CONSULTA · ACTAS ===================== */

    public function records(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $q     = trim((string) $request->query('q', ''));
        $query = VehicleInspection::query();

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('plate', 'like', $like)
                  ->orWhere('vin', 'like', $like)
                  ->orWhere('make', 'like', $like)
                  ->orWhere('model', 'like', $like)
                  ->orWhere('type_name', 'like', $like);
            });
            if (preg_match('/(\d+)/', $q, $m)) {
                $query->orWhere('id', (int) $m[1]);
            }
        }

        $inspections = $query->orderBy('created_at', 'desc')->paginate(30)->withQueryString();

        return view('vehicle.records', compact('inspections', 'q'));
    }

    /* ===================== VISTA LITE (producción) ===================== */

    /**
     * Vista LITE de producción (§9): tarjeta de circulación, placas, licencia, conductor. SIN nivel
     * de riesgo, sin puntos, sin acta. Gate canLite (transport.view O canFull).
     */
    public function lite(Request $request)
    {
        abort_unless(TransportAccess::canLite($request->user()), 403);

        $vehicles = Vehicle::active()
            ->with(['type', 'driver', 'documents.documentType'])
            ->orderBy('make')->orderBy('model')->get();

        return view('vehicle.lite', compact('vehicles'));
    }

    /* ============================ Helpers ============================ */

    private function pickers(): array
    {
        $types  = VehicleType::active()->orderBy('sort_order')->orderBy('name_es')->get();
        $crew   = User::query()->where('activo', 1)->orderBy('name')->get(['id', 'name', 'lname', 'ncreditos']);
        $payees = Schema::hasTable('payees')
            ? Payee::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name'])
            : collect();
        return [$types, $crew, $payees];
    }

    private function validateVehicle(Request $request): array
    {
        return $request->validate([
            'vehicle_type_id' => 'nullable|integer|exists:vehicle_types,id',
            'make'            => 'nullable|string|max:120',
            'model'           => 'nullable|string|max:120',
            'year'            => 'nullable|integer|min:1900|max:2100',
            'color'           => 'nullable|string|max:60',
            'plate'           => 'nullable|string|max:40',
            'vin'             => 'nullable|string|max:60',
            'owner_kind'      => 'required|in:provider,person,other',
            'owner_payee_id'  => 'nullable|integer|exists:payees,id',
            'owner_user_id'   => 'nullable|integer|exists:users,id',
            'owner_name'      => 'nullable|string|max:255',
            'driver_user_id'  => 'nullable|integer|exists:users,id',
            'initial_km'      => 'nullable|integer|min:0|max:9999999',
            'notes'           => 'nullable|string|max:2000',
            // Atributos (ajustables por unidad); si faltan, se toma el perfil del tipo.
            'powertrain'                    => 'nullable|in:combustion,electric,hybrid',
            'seats'                         => 'nullable|integer|min:0|max:120',
            'water_tank_liters'             => 'nullable|integer|min:0|max:100000',
            'has_cargo_box'                 => 'nullable|boolean',
            'has_lpg_or_sanitary'           => 'nullable|boolean',
            'has_genset_or_heat_appliances' => 'nullable|boolean',
            'tows'                          => 'nullable|boolean',
        ]);
    }

    /** Arma el payload del vehículo (atributos resueltos = perfil del tipo + ajustes del request). */
    private function vehiclePayload(Request $request, array $data, ?VehicleType $type): array
    {
        $base = $type ? $type->profile() : VehicleChecklist::normalizeAttributes([]);

        $override = [];
        foreach (['powertrain', 'seats', 'water_tank_liters'] as $k) {
            if ($request->filled($k)) {
                $override[$k] = $data[$k];
            }
        }
        foreach (VehicleChecklist::BOOL_KEYS as $k) {
            if ($request->has($k)) {
                $override[$k] = $request->boolean($k);
            }
        }
        $attrs = VehicleChecklist::normalizeAttributes(array_merge($base, $override));

        $kind = $data['owner_kind'];
        return [
            'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
            'type_code'       => $type ? $type->code : null,
            'make'            => $data['make'] ?? null,
            'model'           => $data['model'] ?? null,
            'year'            => $data['year'] ?? null,
            'color'           => $data['color'] ?? null,
            'plate'           => $data['plate'] ?? null,
            'vin'             => $data['vin'] ?? null,
            'attr_values'     => $attrs,
            'owner_kind'      => $kind,
            'owner_payee_id'  => $kind === Vehicle::OWNER_PROVIDER ? ($data['owner_payee_id'] ?? null) : null,
            'owner_user_id'   => $kind === Vehicle::OWNER_PERSON   ? ($data['owner_user_id'] ?? null) : null,
            'owner_name'      => $kind === Vehicle::OWNER_OTHER    ? ($data['owner_name'] ?? null) : null,
            'driver_user_id'  => $data['driver_user_id'] ?? null,
            'initial_km'      => $data['initial_km'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ];
    }

    private function currentShootDay()
    {
        try {
            return ProductionCalendar::shootDayFor(now()->toDateString());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
