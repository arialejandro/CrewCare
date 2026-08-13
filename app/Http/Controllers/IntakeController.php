<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Payee;
use App\Models\PayeeContract;
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

        return view('payee.intake', [
            'payee'        => $payee,
            'isSelf'       => $isSelf,
            'step'         => $step,
            'steps'        => self::STEPS,
            'completed'    => $completed,
            'requiredDocs' => PayeePackage::identityRequirements($prodId, $payee->legal_nature ?: 'fisica', $payee->nationality ?: 'mexicana'),
            'threshold'    => ProductionDocumentSetting::equipmentThresholdFor($prodId),
            'postUrl'      => $this->postUrl($isSelf, $payee),
            'navUrl'       => fn ($s) => $this->navUrl($isSelf, $payee, $s),
        ]);
    }

    // ── Escritura ÚNICA (por sección; paso a paso o todo de una) ──────────────
    private function handleSave(Request $request, Payee $payee, ?User $actor, bool $isSelf)
    {
        $step = $request->input('_step');                       // null = todo de una (compat/tests)
        $sections = ($step && in_array($step, self::STEPS, true)) ? [$step] : self::STEPS;

        // Pre-check beneficiarios 100% ANTES de escribir nada (si toca la sección emergency).
        if (in_array('emergency', $sections, true)) {
            $bens = collect($request->input('beneficiaries', []))->filter(fn ($b) => trim($b['full_name'] ?? '') !== '');
            if ($bens->isNotEmpty() && round($bens->sum(fn ($b) => (float) ($b['percentage'] ?? 0)), 2) !== 100.00) {
                return back()->withInput()->with('error', 'Los beneficiarios deben sumar exactamente 100%.');
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
                    'addr_street' => 'nullable|string|max:160', 'addr_ext_no' => 'nullable|string|max:20',
                    'addr_int_no' => 'nullable|string|max:20', 'addr_colonia' => 'nullable|string|max:120',
                    'addr_municipio' => 'nullable|string|max:120', 'addr_cp' => 'nullable|string|max:10',
                    'addr_city' => 'nullable|string|max:120', 'addr_state' => 'nullable|string|max:120',
                ]);
                $payee->fill($request->only([
                    'name', 'nationality', 'elector_credential', 'marital_status',
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
                    if (trim($r['name'] ?? '') !== '') {
                        $payee->fiscalRegimes()->create(['code' => $r['code'] ?? null, 'name' => $r['name']]);
                    }
                }
                break;

            case 'documents':
                $request->validate(['documents' => 'nullable|array', 'documents.*' => 'nullable|file|mimes:pdf|max:20480']);
                foreach ((array) $request->file('documents', []) as $docTypeId => $file) {
                    if (! $file) {
                        continue;
                    }
                    $dt   = DocumentType::find($docTypeId);
                    $path = $file->storeAs('payee/docs/' . $payee->id, uniqid('doc_') . '.pdf', 'local'); // disco PRIVADO
                    $payee->documents()->create([
                        'level' => 'persona', 'document_type' => $dt ? $dt->name : 'documento', 'document_type_id' => $dt?->id,
                        'issued_at' => $request->input("issued.$docTypeId"), 'result_status' => $request->input("result.$docTypeId"),
                        'photo_path' => $path, 'origen' => 'contractual',
                        'status' => 'presentado',   // RECIBIDO; el cotejo (validated_*) es aparte
                        'is_active' => 1, 'created_by_id' => $actor?->id, // quién subió + (created_at) cuándo
                    ]);
                }
                break;

            case 'emergency':
                $request->validate([
                    'emergency_contact_name' => 'nullable|string|max:160', 'emergency_contact_phone' => 'nullable|string|max:40',
                    'beneficiaries' => 'nullable|array',
                ]);
                $payee->fill($request->only(['emergency_contact_name', 'emergency_contact_phone']));
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

    /** Misma guarda de departamento que el crew: super-admin/all-departments, o quien contrató. */
    private function authorizeContractor(Payee $payee): void
    {
        $actor = auth()->user();
        if ($actor && $actor->can('crew.view.all-departments')) {
            return;
        }
        if ($actor && $payee->user_id && $actor->canManageCrewMember($payee->user)) {
            return;
        }
        $ok = $actor
            ? User::applyContractingScope(PayeeContract::where('payee_id', $payee->id), $actor)->exists()
            : false;
        abort_unless($ok, 403);
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
