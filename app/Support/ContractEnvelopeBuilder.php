<?php

namespace App\Support;

use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractAnnex;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractEnvelopeRecipient;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO C — crear el SOBRE. Agrupa el paquete (carátula+clausulado+anexos), resuelve
 * los TRES papeles POR PUESTO (el puesto define; el sobre CONGELA a la persona), y sella la
 * integridad del paquete. Vacante/duplicado en un puesto → error claro (no se elige por cuenta
 * propia). Un contrato debe estar EMITIDO (con su carátula) antes de crear el sobre.
 */
class ContractEnvelopeBuilder
{
    public static function build(PayeeContract $contract, ?User $actor = null, ?string $contractedEmail = null): ContractEnvelope
    {
        if (! $contract->isEmitted()) {
            throw new ContractEnvelopeException('Emite el contrato (genera su carátula) antes de crear el sobre.');
        }

        $prod  = (int) $contract->production_id;
        $payee = $contract->payee;
        abort_unless($payee, 404);

        $roles   = ContractEnvelopeRecipient::roleLabels();
        $company = trim((string) (Branding::get('company_name', '')));

        $contractedUser = $payee->user;

        // El CONTRATADO se resuelve solo (la persona del contrato). Nombre LEGAL, no el de créditos.
        // `anchor_key` congela a qué `[[firma:...]]` de la plantilla corresponde este destinatario.
        $contractedSpec = [
            'role'       => ContractEnvelopeRecipient::ROLE_CONTRACTED,
            'name'       => $payee->name,
            'email'      => $contractedEmail ?: optional($contractedUser)->email,
            'cargo'      => 'Contratista',
            'anchor_key' => 'contratado',
            'empresa'    => $payee->isMoral() ? $payee->name : null,
            'user_id'    => optional($contractedUser)->id,
            'payee_id'   => $payee->id,
        ];

        // ── LA RUTA (módulo de firma, config global). Dos caminos: ──
        if (SignaturePositions::hasSignerList()) {
            // NUEVO · lista configurable de N firmantes: contratado + cada PUESTO firmante en orden.
            // El puesto DEFINE quién firma; el sobre CONGELA a la persona. Vacante/duplicado → error.
            $route = [$contractedSpec];
            foreach (SignaturePositions::signerEntries() as $entry) {
                $label  = SignaturePositions::entryLabel($entry);
                $signer = $entry === SignaturePositions::DEPT_HOD
                    ? SignaturePositions::departmentHodUser($prod, $contract->department_id, $label)
                    : SignaturePositions::soleUserForPosition($prod, (int) $entry, $label);
                $route[] = [
                    'role'       => ContractEnvelopeRecipient::ROLE_SIGNER,
                    'name'       => trim($signer->name . ' ' . $signer->lname),
                    'email'      => $signer->email,
                    'cargo'      => $label,
                    // Ancla de la plantilla: 'dept_hod' o 'puesto:ID' (espeja anchorCatalog()).
                    'anchor_key' => $entry === SignaturePositions::DEPT_HOD ? 'dept_hod' : 'puesto:' . (int) $entry,
                    'empresa'    => $company,
                    'user_id'    => $signer->id,
                    'payee_id'   => null,
                ];
            }
        } else {
            // CLÁSICO (compat) · preparador / contratado / obliga, en el orden configurable.
            $prepUser = SignaturePositions::soleUserForPosition($prod, SignaturePositions::preparerPositionId(), $roles[ContractEnvelopeRecipient::ROLE_PREPARER]);
            $bindUser = SignaturePositions::soleUserForPosition($prod, SignaturePositions::binderPositionId(),   $roles[ContractEnvelopeRecipient::ROLE_BINDER]);
            $frozen = [
                ContractEnvelopeRecipient::ROLE_CONTRACTED => $contractedSpec,
                ContractEnvelopeRecipient::ROLE_PREPARER => [
                    'role'     => ContractEnvelopeRecipient::ROLE_PREPARER,
                    'name'     => trim($prepUser->name . ' ' . $prepUser->lname),
                    'email'    => $prepUser->email,
                    'cargo'    => optional(Position::find(SignaturePositions::preparerPositionId()))->name ?: $roles[ContractEnvelopeRecipient::ROLE_PREPARER],
                    'empresa'  => $company,
                    'user_id'  => $prepUser->id,
                    'payee_id' => null,
                ],
                ContractEnvelopeRecipient::ROLE_BINDER => [
                    'role'     => ContractEnvelopeRecipient::ROLE_BINDER,
                    'name'     => trim($bindUser->name . ' ' . $bindUser->lname),
                    'email'    => $bindUser->email,
                    'cargo'    => optional(Position::find(SignaturePositions::binderPositionId()))->name ?: $roles[ContractEnvelopeRecipient::ROLE_BINDER],
                    'empresa'  => $company,
                    'user_id'  => $bindUser->id,
                    'payee_id' => null,
                ],
            ];
            $route = [];
            foreach (SignaturePositions::routeOrder() as $role) {
                $route[] = $frozen[$role];
            }
        }

        // ── Snapshot del paquete (byte-intact): carátula + clausulado + anexos activos ──
        $documents = self::snapshotDocuments($contract, $prod);

        return DB::transaction(function () use ($contract, $prod, $documents, $route, $actor) {
            $envelope = ContractEnvelope::create([
                'payee_contract_id' => $contract->id,
                'production_id'     => $prod,
                'status'            => ContractEnvelope::STATUS_DRAFT,
                'documents'         => $documents,
                'created_by_id'     => optional($actor)->id,
            ]);

            // Destinatarios en el ORDEN de la ruta.
            foreach ($route as $i => $spec) {
                $envelope->recipients()->create(array_merge($spec, [
                    'sort_order' => $i,
                    'status'     => ContractEnvelopeRecipient::STATUS_PENDING,
                ]));
            }

            // Sella la INTEGRIDAD del paquete sobre el modelo RE-CONSULTADO (tipos de BD), no el de
            // memoria: así el sello es REPRODUCIBLE al reverificar desde una consulta fresca (el
            // verificador público /verificar/cenv/{uuid}). Sellar en memoria dejaría columnas sin
            // cast (int vs string tras el round-trip) sin casar → "alterado" falso. (FASE 3)
            $envelope = $envelope->fresh();
            $envelope->signDocumentAsSystem('sobre creado');

            // Primer eslabón de la bitácora: el sobre nace registrado.
            ContractEventLog::record($envelope, ContractEnvelopeEvent::CREATED, [
                'actor_id' => optional($actor)->id,
                'payload'  => ['recipients' => count($route), 'documents' => count($documents)],
            ]);

            return $envelope->fresh('recipients');
        });
    }

    private static function snapshotDocuments(PayeeContract $contract, int $prod): array
    {
        $docs = [];
        $hash = fn ($path) => ($path && Storage::disk('local')->exists($path))
            ? hash('sha256', Storage::disk('local')->get($path)) : null;

        if ($contract->caratula_path) {
            $docs[] = ['kind' => 'caratula', 'name' => 'Carátula', 'path' => $contract->caratula_path, 'hash' => $hash($contract->caratula_path)];
        }
        if ($clause = $contract->clause) {
            $docs[] = ['kind' => 'clause', 'name' => $clause->name, 'path' => $clause->file_path, 'hash' => $clause->file_hash];
        }
        foreach (ContractAnnex::forProduction($prod)->active()->orderBy('sort_order')->get() as $a) {
            $docs[] = ['kind' => 'annex', 'name' => $a->name, 'path' => $a->file_path, 'hash' => $a->file_hash, 'annex_id' => $a->id];
        }
        return $docs;
    }
}
