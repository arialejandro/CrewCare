<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\ProductionDocumentSetting;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\PayeePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * PASO 3 — INTAKE AUTOSERVICIO + captura por quien contrata. DOS superficies, UN destino:
 * ambas escriben en payees / payee_fiscal_regimes / payee_beneficiaries /
 * payee_declared_equipment / external_authorizations (ledger). Un solo camino de escritura
 * ({@see persist()}).
 *
 *  - SELF: la persona invitada por URL FIRMADA (la firma es su llave; no hay login).
 *  - CONTRACTOR: usuario autenticado que captura por un proveedor que no es usuario;
 *    guardado con la MISMA guarda de departamento que el crew.
 *
 * El documento subido queda RECIBIDO (nunca "validado"/"cumple"). El cotejo es aparte
 * (validated_* del ledger). El estado real lo deriva {@see PayeePackage}.
 */
class IntakeController extends Controller
{
    /** Invitación firmada al intake (patrón Magic Links). La dispara el alta o el hub. */
    public static function invitationUrl(User $user, int $days = 14): string
    {
        return URL::temporarySignedRoute('intake.show', now()->addDays($days), ['user' => $user->id]);
    }

    // ── SELF (URL firmada) ────────────────────────────────────────────────────
    public function show(Request $request, User $user)
    {
        $payee = $this->resolveSelfPayee($user);
        return view('payee.intake', $this->formData($payee, $user, false));
    }

    public function store(Request $request, User $user)
    {
        $payee = $this->resolveSelfPayee($user);
        abort_unless((int) $payee->user_id === (int) $user->id, 403); // solo su propio intake
        $result = $this->persist($request, $payee, $user);
        if ($result !== true) {
            return back()->withInput()->with('error', $result);
        }
        return view('payee.intake-thanks', ['name' => trim($user->name . ' ' . $user->lname)]);
    }

    // ── CONTRACTOR (autenticado + guarda de departamento) ─────────────────────
    public function contractorForm(Request $request, Payee $payee)
    {
        $this->authorizeContractor($payee);
        return view('payee.intake', $this->formData($payee, $payee->user, true));
    }

    public function contractorStore(Request $request, Payee $payee)
    {
        $this->authorizeContractor($payee);
        $result = $this->persist($request, $payee, auth()->user());
        if ($result !== true) {
            return back()->withInput()->with('error', $result);
        }
        return redirect('/payees/' . $payee->id . '/intake')->with('success', 'Intake guardado. Documentos: RECIBIDOS.');
    }

    // ── Resolución + guarda ───────────────────────────────────────────────────
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
        // Proveedor sin usuario: visible para el departamento que lo contrató (hermano del scope).
        $ok = $actor
            ? User::applyContractingScope(PayeeContract::where('payee_id', $payee->id), $actor)->exists()
            : false;
        abort_unless($ok, 403);
    }

    // ── Escritura ÚNICA ───────────────────────────────────────────────────────
    /** @return true|string  true = ok; string = mensaje de error (no se guardó). */
    private function persist(Request $request, Payee $payee, ?User $actor)
    {
        $prodId    = optional(CurrentProduction::get())->id;
        $threshold = ProductionDocumentSetting::equipmentThresholdFor($prodId);

        $request->validate([
            'name'                => 'nullable|string|max:255',
            'nationality'         => 'nullable|in:mexicana,extranjera',
            'elector_credential'  => 'nullable|string|max:30',
            'marital_status'      => 'nullable|string|max:30',
            'rfc'                 => 'nullable|string|max:20',
            'tax_residence_country' => 'nullable|string|max:80',
            'bank_name'           => 'nullable|string|max:120',
            'bank_branch'         => 'nullable|string|max:120',
            'bank_account'        => 'nullable|string|max:40',
            'bank_clabe'          => 'nullable|string|max:24',
            'addr_street'         => 'nullable|string|max:160',
            'addr_cp'             => 'nullable|string|max:10',
            'emergency_contact_name'  => 'nullable|string|max:160',
            'emergency_contact_phone' => 'nullable|string|max:40',
            'shirt_size'          => 'nullable|string|max:10',
            'regimes'             => 'nullable|array',
            'beneficiaries'       => 'nullable|array',
            'declared_equipment'  => 'nullable|array',
            'documents'           => 'nullable|array',
            'documents.*'         => 'nullable|file|mimes:pdf|max:20480', // solo PDF; disco privado
        ]);

        // Beneficiarios: si hay, deben sumar 100% — si no, NO se guarda nada.
        $bens = collect($request->input('beneficiaries', []))
            ->filter(fn ($b) => trim($b['full_name'] ?? '') !== '')->values();
        if ($bens->isNotEmpty() && round($bens->sum(fn ($b) => (float) ($b['percentage'] ?? 0)), 2) !== 100.00) {
            return 'Los beneficiarios deben sumar exactamente 100%.';
        }

        DB::transaction(function () use ($request, $payee, $actor, $bens, $threshold) {
            // 1) Identidad + fiscales + logística en payees.
            $payee->fill($request->only([
                'name', 'nationality', 'elector_credential', 'marital_status',
                'rfc', 'tax_residence_country', 'bank_name', 'bank_branch', 'bank_account', 'bank_clabe',
                'addr_street', 'addr_ext_no', 'addr_int_no', 'addr_colonia', 'addr_municipio',
                'addr_cp', 'addr_city', 'addr_state',
                'emergency_contact_name', 'emergency_contact_phone', 'shirt_size',
            ]));
            $payee->is_vegetarian = $request->boolean('is_vegetarian');
            $payee->is_donor      = $request->boolean('is_donor');
            $payee->intake_submitted_at = now();
            if (! $payee->created_by_id && $actor) {
                $payee->created_by_id = $actor->id;
            }
            $payee->save();

            // 2) Regímenes fiscales (N). Reemplaza el conjunto.
            $payee->fiscalRegimes()->delete();
            foreach ((array) $request->input('regimes', []) as $r) {
                if (trim($r['name'] ?? '') !== '') {
                    $payee->fiscalRegimes()->create(['code' => $r['code'] ?? null, 'name' => $r['name']]);
                }
            }

            // 3) Beneficiarios (dato mínimo de terceros). Reemplaza el conjunto.
            $payee->beneficiaries()->delete();
            foreach ($bens as $i => $b) {
                $payee->beneficiaries()->create([
                    'full_name'    => $b['full_name'],
                    'relationship' => $b['relationship'] ?? null,
                    'percentage'   => (float) ($b['percentage'] ?? 0),
                    'sort_order'   => $i,
                ]);
            }

            // 4) Equipo declarado (solo sobre el umbral) → DECLARACIÓN firmada (capa simple).
            foreach ((array) $request->input('declared_equipment', []) as $e) {
                $desc = trim($e['description'] ?? '');
                $val  = (float) ($e['declared_value'] ?? 0);
                if ($desc === '' || $val <= $threshold) {
                    continue; // solo equipo con valor superior al umbral
                }
                $eq = $payee->declaredEquipment()->create([
                    'description'      => $desc,
                    'invoice_holder'   => $e['invoice_holder'] ?? null,
                    'declared_value'   => $val,
                    'acceptor_user_id' => $actor?->id,
                    'acceptor_name'    => $actor ? trim($actor->name . ' ' . $actor->lname) : null,
                    'acceptor_role'    => $actor ? optional($actor->getRoleNames())->first() : null,
                    'accepted_at'      => now(),
                    'is_active'        => 1,
                ]);
                $eq->signDocument($actor, $request); // sello SHA + rastro (quién/ip/cuándo)
            }

            // 5) Documentos del paquete → RECIBIDO (nunca validado). Quién subió = created_by_id.
            foreach ((array) $request->file('documents', []) as $docTypeId => $file) {
                if (! $file) {
                    continue;
                }
                $dt   = DocumentType::find($docTypeId);
                $path = $file->storeAs('payee/docs/' . $payee->id, uniqid('doc_') . '.pdf', 'local'); // disco PRIVADO
                $payee->documents()->create([
                    'level'            => 'persona',
                    'document_type'    => $dt ? $dt->name : 'documento',
                    'document_type_id' => $dt?->id,
                    'issued_at'        => $request->input("issued.$docTypeId"),
                    'result_status'    => $request->input("result.$docTypeId"),
                    'photo_path'       => $path,
                    'origen'           => 'contractual',
                    'status'           => 'presentado',   // RECIBIDO; el cotejo (validated_*) es aparte
                    'is_active'        => 1,
                    'created_by_id'    => $actor?->id,     // quién subió y (created_at) cuándo
                ]);
            }
        });

        return true;
    }

    /** Datos para la vista: el paquete que le toca por naturaleza + lo ya capturado. */
    private function formData(Payee $payee, ?User $user, bool $isContractor): array
    {
        $prodId = optional(CurrentProduction::get())->id;
        return [
            'payee'         => $payee->load(['fiscalRegimes', 'beneficiaries', 'declaredEquipment', 'documents']),
            'user'          => $user,
            'isContractor'  => $isContractor,
            'requiredDocs'  => PayeePackage::identityRequirements($prodId, $payee->legal_nature ?: 'fisica'),
            'threshold'     => ProductionDocumentSetting::equipmentThresholdFor($prodId),
        ];
    }
}
