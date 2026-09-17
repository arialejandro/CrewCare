<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Payee;
use App\Models\PaymentPeriod;
use App\Models\ProductionDocumentSetting;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\PayeePackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * PASO 3 — INTAKE AUTOSERVICIO (asistente por pasos) + captura por quien contrata. DOS
 * superficies, UN destino y UN camino de escritura ({@see saveSection()}):
 *  - SELF: la persona invitada por URL FIRMADA/expirable (la firma es su llave; sin login).
 *  - CONTRACTOR: usuario autenticado que captura por un proveedor que no es usuario; MISMA
 *    guarda de departamento que el crew. Misma vista, misma escritura.
 *
 * GUARDADO PARCIAL EN EL SERVIDOR: la fila del payee ya existe, así que puede quedar
 * incompleta. Cada paso guarda lo suyo; al reabrir el enlace desde cualquier dispositivo se
 * retoma donde se quedó (el estado vive en el servidor, no solo en IndexedDB de cc-drafts).
 *
 * El documento subido queda RECIBIDO (nunca "validado"/"cumple"); el cotejo es aparte.
 */
class IntakeController extends Controller
{
    /** Los 6 pasos del asistente, en orden. */
    const STEPS = ['identity', 'fiscal', 'documents', 'emergency', 'equipment', 'logistics'];

    /** Segundo factor SELF: intentos permitidos por enlace antes de enfriar (server-side). */
    const SF_MAX = 8;

    /** Invitación firmada al intake (patrón Magic Links). La dispara el alta o el hub. */
    public static function invitationUrl(User $user, int $days = 14): string
    {
        return URL::temporarySignedRoute('intake.show', now()->addDays($days), ['user' => $user->id]);
    }

    // ── SELF (URL firmada) ────────────────────────────────────────────────────
    public function show(Request $request, User $user)
    {
        $payee = $this->resolveSelfPayee($user);
        if ($this->secondFactorRequired($user) && ! $this->secondFactorPassed($user)) {
            return $this->gateView($user, null, false); // pide fecha de nacimiento antes del asistente
        }
        return $this->renderWizard($request, $payee, true);
    }

    /**
     * SEGUNDO FACTOR (solo SELF): coteja la FECHA DE NACIMIENTO contra users.borndate (el alta la
     * exige). NO ES MURO: si no coincide, avisa sin bloquear (el dato pudo capturarse mal → se
     * corrige con producción); si nunca se capturó, se OMITE. Intentos LIMITADOS server-side
     * (RateLimiter, no depende de cookies) y recordado en sesión (una vez por dispositivo).
     */
    public function verify(Request $request, User $user)
    {
        // Sin fecha en el registro no hay nada que cotejar: no se puede dejar fuera a la persona.
        if (! $this->secondFactorRequired($user)) {
            session()->put($this->sfSessionKey($user), true);
            return redirect(URL::temporarySignedRoute('intake.show', now()->addDays(14), ['user' => $user->id]));
        }

        $key = $this->sfLimiterKey($user);
        if (RateLimiter::tooManyAttempts($key, self::SF_MAX)) {
            return $this->gateView($user,
                'Demasiados intentos por ahora. Si la fecha sigue sin coincidir, avísale a la producción para corregir tu registro.',
                true);
        }
        RateLimiter::hit($key, 3600); // ventana de 1 h

        $given = trim((string) $request->input('borndate'));
        if ($given !== '' && $given === $this->userBirthdate($user)) {
            RateLimiter::clear($key);
            session()->put($this->sfSessionKey($user), true);
            return redirect(URL::temporarySignedRoute('intake.show', now()->addDays(14), ['user' => $user->id]));
        }

        $left = max(0, self::SF_MAX - RateLimiter::attempts($key));
        return $this->gateView($user,
            'La fecha no coincide con tu registro. Si crees que es un error, avísale a la producción para corregirlo.'
            . ($left > 0 ? " Intentos restantes: {$left}." : ''),
            $left <= 0);
    }

    public function store(Request $request, User $user)
    {
        $payee = $this->resolveSelfPayee($user);
        abort_unless((int) $payee->user_id === (int) $user->id, 403); // solo su propio intake
        // El POST directo tampoco salta el segundo factor (quien tiene el enlace debe cotejar primero).
        if ($this->secondFactorRequired($user) && ! $this->secondFactorPassed($user)) {
            return $this->gateView($user, 'Confirma tu fecha de nacimiento antes de continuar.', false);
        }
        return $this->handleSave($request, $payee, $user, true);
    }

    // ── CONTRACTOR (autenticado + guarda de departamento) ─────────────────────
    public function contractorForm(Request $request, Payee $payee)
    {
        $this->authorizeContractor($payee);
        return $this->renderWizard($request, $payee, false);
    }

    public function contractorStore(Request $request, Payee $payee)
    {
        $this->authorizeContractor($payee);
        return $this->handleSave($request, $payee, auth()->user(), false);
    }

    // ── Render del asistente ──────────────────────────────────────────────────
    private function renderWizard(Request $request, Payee $payee, bool $isSelf)
    {
        $payee->load(['fiscalRegimes', 'beneficiaries', 'declaredEquipment', 'documents']);
        $prodId = optional(CurrentProduction::get())->id;

        $completed = $this->completedSteps($payee);
        $step = $request->query('step');
        if (! in_array($step, self::STEPS, true)) {
            // Retoma en el primer paso incompleto (o el primero).
            $step = collect(self::STEPS)->first(fn ($s) => ! $completed[$s]) ?: self::STEPS[0];
        }

        // El Certificado de seguro (COI) no se pide en el intake de la persona (no es doc de
        // captura del titular); si algún día se requiere, se administra en el paquete/facturación.
        $requiredDocs = PayeePackage::identityRequirements($prodId, $payee->legal_nature ?: 'fisica', $payee->nationality ?: 'mexicana')
            ->reject(fn ($d) => $d->code === 'COI')
            ->values();

        return view('payee.intake', [
            'payee'          => $payee,
            'isSelf'         => $isSelf,
            'step'           => $step,
            'steps'          => self::STEPS,
            'completed'      => $completed,
            'requiredDocs'   => $requiredDocs,
            'regimenOptions' => \App\Support\SatCatalogs::regimenesFor($payee->legal_nature ?: 'fisica'),
            'threshold'      => ProductionDocumentSetting::equipmentThresholdFor($prodId),
            'postUrl'        => $this->postUrl($isSelf, $payee),
            'navUrl'         => fn ($s) => $this->navUrl($isSelf, $payee, $s),
        ]);
    }

    // ── Escritura ÚNICA (por sección; paso a paso o todo de una) ──────────────
    private function handleSave(Request $request, Payee $payee, ?User $actor, bool $isSelf)
    {
        $step = $request->input('_step');                       // null = todo de una (compat/tests)
        $sections = ($step && in_array($step, self::STEPS, true)) ? [$step] : self::STEPS;

        // Pre-check beneficiarios ANTES de escribir nada (si toca la sección emergency). Los
        // beneficiarios son OPCIONALES (sin ninguno se avanza), pero si nombras al menos uno:
        // cada uno necesita % > 0 y el conjunto debe sumar EXACTAMENTE 100. (El nombre + parentesco
        // + % de cada fila llegan juntos gracias a los índices explícitos del formulario.)
        if (in_array('emergency', $sections, true)) {
            $bens = collect($request->input('beneficiaries', []))->filter(fn ($b) => trim($b['full_name'] ?? '') !== '');
            if ($bens->isNotEmpty()) {
                $sum        = round($bens->sum(fn ($b) => (float) ($b['percentage'] ?? 0)), 2);
                $anyInvalid = $bens->contains(fn ($b) => (float) ($b['percentage'] ?? 0) <= 0);
                if ($anyInvalid || $sum !== 100.00) {
                    return back()->withInput()->with('error', 'Cada beneficiario necesita un porcentaje y juntos deben sumar exactamente 100%.');
                }
            }
        }

        // Pre-check EQUIPO (solo en el PASO del asistente, no en el full-submit de compat): no se
        // avanza en blanco. O se DECLARA equipo (al menos una fila con descripción) o se MARCA
        // "Acepto que lo no declarado no queda cubierto" — decisión explícita.
        if ($step === 'equipment') {
            $hasRow = collect($request->input('declared_equipment', []))->contains(fn ($e) => trim($e['description'] ?? '') !== '');
            if (! $hasRow && ! $request->boolean('accept_equipment')) {
                return back()->withInput()->with('error', 'En Equipo: agrega al menos un equipo, o marca “Acepto que lo no declarado no queda cubierto” para continuar.');
            }
        }

        DB::transaction(function () use ($request, $payee, $actor, $sections) {
            foreach ($sections as $s) {
                $this->saveSection($request, $payee, $actor, $s);
            }
            if (! $request->input('_step') || $request->input('_step') === 'logistics') {
                $payee->intake_submitted_at = now();   // finaliza al terminar (o en el full submit)
                $payee->save();
            }
        });

        // Navegación: avanza al siguiente paso, o termina.
        if ($step && $step !== 'logistics') {
            $next = self::STEPS[array_search($step, self::STEPS, true) + 1];
            return redirect($this->navUrl($isSelf, $payee, $next));
        }
        if ($isSelf) {
            return view('payee.intake-thanks', ['name' => trim(($actor->name ?? '') . ' ' . ($actor->lname ?? ''))]);
        }
        return redirect('/payees/' . $payee->id . '/intake')->with('success', 'Intake guardado. Documentos: RECIBIDOS.');
    }

    private function saveSection(Request $request, Payee $payee, ?User $actor, string $section): void
    {
        switch ($section) {
            case 'identity':
                $request->validate([
                    'name' => 'nullable|string|max:255', 'nationality' => 'nullable|in:mexicana,extranjera',
                    'elector_credential' => 'nullable|string|max:30', 'marital_status' => 'nullable|string|max:30',
                    'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:191',
                    'legal_representative' => 'nullable|string|max:200',
                    'addr_street' => 'nullable|string|max:160', 'addr_ext_no' => 'nullable|string|max:20',
                    'addr_int_no' => 'nullable|string|max:20', 'addr_colonia' => 'nullable|string|max:120',
                    'addr_municipio' => 'nullable|string|max:120', 'addr_cp' => 'nullable|string|max:10',
                    'addr_city' => 'nullable|string|max:120', 'addr_state' => 'nullable|string|max:120',
                ]);
                $payee->fill($request->only([
                    'name', 'nationality', 'elector_credential', 'marital_status',
                    'phone', 'email', 'legal_representative',
                    'addr_street', 'addr_ext_no', 'addr_int_no', 'addr_colonia', 'addr_municipio',
                    'addr_cp', 'addr_city', 'addr_state',
                ]));
                if (! $payee->created_by_id && $actor) {
                    $payee->created_by_id = $actor->id;
                }
                $payee->save();
                break;

            case 'fiscal':
                $request->validate([
                    'rfc' => 'nullable|string|max:20', 'tax_residence_country' => 'nullable|string|max:80',
                    'bank_name' => 'nullable|string|max:120', 'bank_branch' => 'nullable|string|max:120',
                    'bank_account' => 'nullable|string|max:40', 'bank_clabe' => 'nullable|string|max:24',
                    'regimes' => 'nullable|array',
                ]);
                $payee->fill($request->only(['rfc', 'tax_residence_country', 'bank_name', 'bank_branch', 'bank_account', 'bank_clabe']));
                $payee->save();
                $payee->fiscalRegimes()->delete();
                foreach ((array) $request->input('regimes', []) as $r) {
                    $code = trim((string) ($r['code'] ?? ''));
                    // El NOMBRE lo deriva el catálogo SAT desde la clave (el dropdown manda solo la
                    // clave); fallback al nombre posteado si la clave no está en el catálogo (compat).
                    $name = \App\Support\SatCatalogs::regimenName($code) ?? trim((string) ($r['name'] ?? ''));
                    if ($code !== '' || $name !== '') {
                        $payee->fiscalRegimes()->create(['code' => $code ?: null, 'name' => $name !== '' ? $name : $code]);
                    }
                }
                break;

            case 'documents':
                $request->validate([
                    'documents' => 'nullable|array', 'documents.*' => 'nullable|file|mimes:pdf|max:20480',
                    // XML de la factura (CFDI): OPCIONAL junto al PDF. Aditivo → NO rompe el flujo PDF-only
                    // existente (por eso no es required). mimes xml/txt (algunos XML se detectan text/plain).
                    'documents_xml' => 'nullable|array', 'documents_xml.*' => 'nullable|file|mimes:xml,txt|max:5120',
                    // Folio de la 32-D (OPCIONAL): un solo campo para el enlace del SAT; si no viene, se
                    // captura después desde el tablero. Campo DEDICADO sat_folio (no el `folio` de trámite).
                    'sat_folio' => 'nullable|array', 'sat_folio.*' => 'nullable|string|max:60',
                ]);
                $cfdiWarnings = [];
                // Prefijos que el parser reconoce en las descripciones = el CATÁLOGO editable de conceptos
                // (posición libre). Agregar un prefijo nuevo allí lo hace reconocible, sin tocar código.
                $conceptCodes = \App\Models\PaymentConcept::forProduction(CurrentProduction::id())->pluck('code')->all();
                foreach ((array) $request->file('documents', []) as $docTypeId => $file) {
                    if (! $file) {
                        continue;
                    }
                    $dt   = DocumentType::find($docTypeId);
                    // Carpeta por DEPARTAMENTO → por PERSONA (dentro del bucket global payee/docs):
                    // payee/docs/{deptSlug}/{payeeId}/. Se sirve por photo_path guardado, así que los
                    // docs viejos (layout plano) siguen resolviendo; solo las subidas nuevas se organizan.
                    $path = $file->storeAs('payee/docs/' . $payee->departmentSlug() . '/' . $payee->id, uniqid('doc_') . '.pdf', 'local'); // disco PRIVADO
                    $doc = $payee->documents()->create([
                        'level' => 'persona', 'document_type' => $dt ? $dt->name : 'documento', 'document_type_id' => $dt?->id,
                        'issued_at' => $request->input("issued.$docTypeId"), 'result_status' => $request->input("result.$docTypeId"),
                        'sat_folio' => trim((string) $request->input("sat_folio.$docTypeId")) ?: null,   // 32-D (opcional)
                        'photo_path' => $path, 'origen' => 'contractual',
                        'status' => 'presentado',   // RECIBIDO; el cotejo (validated_*) es aparte
                        'is_active' => 1, 'created_by_id' => $actor?->id, // quién subió + (created_at) cuándo
                    ]);

                    // XML DE LA FACTURA (CFDI): si el tipo ES factura y llegó su XML, se guarda, se PARSEA
                    // (folio fiscal/RFCs/total/sello para el enlace) y se COTEJA el RFC emisor contra el del
                    // payee. RECIBIR NO ES VALIDAR: comparar dos datos que ya tienes no valida nada.
                    // Best-effort: un XML ilegible NO rompe la recepción del PDF (queda como está).
                    if ($dt && $dt->expects_cfdi_xml) {
                        $xmlFile = $request->file("documents_xml.$docTypeId");
                        if ($xmlFile) {
                            try {
                                $xmlPath = $xmlFile->storeAs('payee/docs/' . $payee->departmentSlug() . '/' . $payee->id, uniqid('cfdi_') . '.xml', 'local');
                                $cfdi = \App\Support\CfdiParser::parse((string) \Illuminate\Support\Facades\Storage::disk('local')->get($xmlPath), null, $conceptCodes);
                                if ($cfdi) {
                                    $doc->update([
                                        'xml_path'          => $xmlPath,
                                        'cfdi_uuid'         => $cfdi['uuid'] ?: null,
                                        'cfdi_rfc_emisor'   => $cfdi['rfc_emisor'] ?: null,
                                        'cfdi_rfc_receptor' => $cfdi['rfc_receptor'] ?: null,
                                        'cfdi_total'        => $cfdi['total'] ?: null,
                                        'cfdi_sello'        => $cfdi['sello'] ?: null,
                                        'cfdi_conceptos'    => ! empty($cfdi['conceptos']) ? $cfdi['conceptos'] : null,
                                    ]);
                                    $payeeRfc = strtoupper(trim((string) $payee->rfc));
                                    if ($payeeRfc !== '' && $cfdi['rfc_emisor'] !== '' && $payeeRfc !== $cfdi['rfc_emisor']) {
                                        $cfdiWarnings[] = ($dt->name ?: 'Factura') . ': el RFC emisor del XML (' . $cfdi['rfc_emisor'] . ') no coincide con el del proveedor (' . $payeeRfc . ').';
                                    }
                                } else {
                                    $cfdiWarnings[] = ($dt->name ?: 'Factura') . ': el XML no se leyó como CFDI; se recibió el PDF, sin enlace de verificación.';
                                }
                            } catch (\Throwable $e) {
                                $cfdiWarnings[] = ($dt->name ?: 'Factura') . ': no se pudo procesar el XML; se recibió el PDF.';
                            }
                        }
                    }

                    // 32-D: extrae el FOLIO (+ fecha/sentido) de la CADENA ORIGINAL del PDF (texto
                    // estructurado, no OCR). El acuse SIEMPRE es PDF con texto; si un PDF raro no la trae,
                    // el folio se captura a mano (fallback intacto). El folio TECLEADO gana sobre lo
                    // extraído. 🔴 No consulta al SAT ni valida: solo pre-llena lo que arma el enlace.
                    if ($dt && $dt->code === 'OPINION_32D' && trim((string) $path) !== '') {
                        $c32 = \App\Support\Sat32dReader::fromPdf(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
                        if ($c32) {
                            $updates = [];
                            if (trim((string) $doc->sat_folio) === '' && $c32['folio'] !== '')       { $updates['sat_folio'] = $c32['folio']; }
                            if ($doc->issued_at === null && $c32['fecha'])                            { $updates['issued_at'] = $c32['fecha']; }
                            if (trim((string) $doc->result_status) === '' && $c32['sentido'])         { $updates['result_status'] = $c32['sentido']; }
                            if ($updates) { $doc->update($updates); }
                            $payeeRfc = strtoupper(trim((string) $payee->rfc));
                            if ($payeeRfc !== '' && $c32['rfc'] !== '' && $payeeRfc !== $c32['rfc']) {
                                $cfdiWarnings[] = 'Opinión 32-D: el RFC de la cadena (' . $c32['rfc'] . ') no coincide con el del proveedor (' . $payeeRfc . ').';
                            }
                        }
                    }

                    // VENTANA DE RECEPCIÓN: cuelga el documento del periodo de pago (dentro o fuera de
                    // ventana). Best-effort: si falla, la captura NO se rompe y el tablero igual deriva
                    // el estado con PayeePackage.
                    try {
                        $res = PaymentPeriod::resolveReception($payee);
                        if ($res['period']) {
                            $doc->update([
                                'payment_period_id'      => $res['period']->id,
                                'received_out_of_window' => $res['out_of_window'],
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // silencioso a propósito: el estampado del periodo no puede impedir la recepción.
                    }
                }
                if (! empty($cfdiWarnings)) {
                    session()->flash('warning', implode(' ', $cfdiWarnings));
                }
                break;

            case 'emergency':
                $request->validate([
                    'emergency_contact_name' => 'nullable|string|max:160', 'emergency_contact_phone' => 'nullable|string|max:40',
                    'emergency_contact_relationship' => 'nullable|string|max:60',
                    'beneficiaries' => 'nullable|array',
                ]);
                $payee->fill($request->only(['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship']));
                $payee->save();
                $bens = collect($request->input('beneficiaries', []))->filter(fn ($b) => trim($b['full_name'] ?? '') !== '')->values();
                $payee->beneficiaries()->delete();
                foreach ($bens as $i => $b) {
                    $payee->beneficiaries()->create([
                        'full_name' => $b['full_name'], 'relationship' => $b['relationship'] ?? null,
                        'percentage' => (float) ($b['percentage'] ?? 0), 'sort_order' => $i,
                    ]);
                }
                break;

            case 'equipment':
                $request->validate(['declared_equipment' => 'nullable|array']);
                $threshold = ProductionDocumentSetting::equipmentThresholdFor(optional(CurrentProduction::get())->id);
                $payee->declaredEquipment()->delete(); // reemplaza el conjunto (re-declara + re-firma)
                foreach ((array) $request->input('declared_equipment', []) as $e) {
                    $desc = trim($e['description'] ?? '');
                    $val  = (float) ($e['declared_value'] ?? 0);
                    if ($desc === '' || $val <= $threshold) {
                        continue; // solo equipo con valor superior al umbral
                    }
                    $eq = $payee->declaredEquipment()->create([
                        'description' => $desc, 'invoice_holder' => $e['invoice_holder'] ?? null, 'declared_value' => $val,
                        'acceptor_user_id' => $actor?->id, 'acceptor_name' => $actor ? trim($actor->name . ' ' . $actor->lname) : null,
                        'acceptor_role' => $actor ? optional($actor->getRoleNames())->first() : null,
                        'accepted_at' => now(), 'is_active' => 1,
                    ]);
                    $eq->signDocument($actor, $request); // capa simple: sello SHA + rastro
                }
                break;

            case 'logistics':
                $request->validate(['shirt_size' => 'nullable|string|max:10']);
                $payee->fill($request->only(['shirt_size']));
                $payee->is_vegetarian = $request->boolean('is_vegetarian');
                $payee->is_donor      = $request->boolean('is_donor');
                $payee->save();
                break;
        }
    }

    // ── Segundo factor (SELF) ─────────────────────────────────────────────────
    /** ¿Hay fecha de nacimiento con qué cotejar? Sin ella el factor se OMITE (no bloquea). */
    private function secondFactorRequired(User $user): bool
    {
        return $this->userBirthdate($user) !== null;
    }

    /** ¿Ya se cotejó en ESTE dispositivo (sesión)? */
    private function secondFactorPassed(User $user): bool
    {
        return (bool) session($this->sfSessionKey($user), false);
    }

    /** Fecha de nacimiento normalizada a Y-m-d, o null si no está capturada. */
    private function userBirthdate(User $user): ?string
    {
        return $user->borndate ? Carbon::parse($user->borndate)->format('Y-m-d') : null;
    }

    private function sfSessionKey(User $user): string
    {
        return "intake_2fa_ok.{$user->id}";
    }

    private function sfLimiterKey(User $user): string
    {
        return 'intake-2fa:' . $user->id;
    }

    /** Pantalla del segundo factor (autocontenida). El POST va FIRMADO a intake.verify. */
    private function gateView(User $user, ?string $error, bool $locked)
    {
        return view('payee.intake-gate', [
            'who'     => trim($user->name . ' ' . $user->lname) ?: 'esta persona',
            'postUrl' => URL::temporarySignedRoute('intake.verify', now()->addDays(14), ['user' => $user->id]),
            'error'   => $error,
            'locked'  => $locked,
        ]);
    }

    // ── Resolución + guarda + navegación ──────────────────────────────────────
    private function resolveSelfPayee(User $user): Payee
    {
        return Payee::firstOrCreate(
            ['user_id' => $user->id],
            ['legal_nature' => Payee::NATURE_FISICA, 'name' => trim($user->name . ' ' . $user->lname), 'is_active' => 1]
        );
    }

    /**
     * Guarda de la captura por quien contrata: MISMO criterio que la visibilidad del Paso 4
     * ({@see \App\Policies\PayeePolicy::capture} → {@see Payee::scopeVisibleTo}). Fuente única:
     * "quien contrata es quien ve" + liga de crew por departamento, bypass all-departments,
     * super-admin por Gate::before.
     */
    private function authorizeContractor(Payee $payee): void
    {
        abort_unless(auth()->check() && auth()->user()->can('capture', $payee), 403);
    }

    /** URL de navegación (GET) del paso: firmada para SELF, ruta normal para el contratante. */
    private function navUrl(bool $isSelf, Payee $payee, string $step): string
    {
        return $isSelf
            ? URL::temporarySignedRoute('intake.show', now()->addDays(14), ['user' => $payee->user_id, 'step' => $step])
            : route('payee.intake.form', ['payee' => $payee->id, 'step' => $step]);
    }

    /** URL de guardado (POST): firmada para SELF, ruta normal para el contratante. */
    private function postUrl(bool $isSelf, Payee $payee): string
    {
        return $isSelf
            ? URL::temporarySignedRoute('intake.store', now()->addDays(14), ['user' => $payee->user_id])
            : route('payee.intake.store', ['payee' => $payee->id]);
    }

    /** Qué pasos tienen contenido (para el indicador de avance). Heurística por sección. */
    private function completedSteps(Payee $payee): array
    {
        return [
            'identity'  => (bool) ($payee->nationality || $payee->addr_cp || $payee->elector_credential),
            'fiscal'    => (bool) ($payee->rfc || $payee->bank_clabe || $payee->fiscalRegimes->isNotEmpty()),
            'documents' => $payee->documents->isNotEmpty(),
            'emergency' => (bool) ($payee->emergency_contact_name || $payee->beneficiaries->isNotEmpty()),
            'equipment' => $payee->declaredEquipment->isNotEmpty(),
            'logistics' => (bool) $payee->shirt_size,
        ];
    }
}
