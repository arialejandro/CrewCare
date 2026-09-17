<?php

namespace App\Support;

use App\Models\ContractClause;
use App\Models\PayeeContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO B — LA CARÁTULA. UNA plantilla NEUTRA (tabla etiqueta/valor de dos columnas,
 * como las cinco carátulas reales). Se genera con dompdf (es una tabla; la fidelidad de Chrome se
 * queda para lo visual). Reglas:
 *  - CERO MARCA DE CREWCARE: el logo es el `client_logo` de la productora (NUNCA el default de
 *    CrewCare) y el contratante son los datos de la productora, no la marca.
 *  - Un campo VACÍO no imprime su renglón (nada de "N/A" ni relleno).
 *  - Idioma seleccionable: es | en | bilingual (etiquetas en ambos idiomas a doble columna).
 *  - Contratante: si el contrato YA se emitió, usa lo CONGELADO en el contrato; si no, lee settings
 *    (vista previa). Editar settings después NO cambia un emitido (sus columnas quedaron congeladas).
 */
class ContractCoverSheet
{
    /**
     * Valores del contratante desde SETTINGS (para vista previa y para congelar al emitir).
     * Claves: `company_name`/`office_address` (ya existían) + `rfc`/`representante_legal`/
     * `correo_contratante` (nuevas de B5).
     */
    public static function contractorFromSettings(): array
    {
        $b = Branding::all();
        return [
            'legal_name'     => trim((string) ($b['company_name'] ?? '')),
            'rfc'            => trim((string) ($b['rfc'] ?? '')),
            'address'        => trim((string) ($b['office_address'] ?? '')),
            'representative' => trim((string) ($b['representante_legal'] ?? '')),
            'email'          => trim((string) ($b['correo_contratante'] ?? '')),
        ];
    }

    /** Campos del contratante FALTANTES en settings (para avisar claro antes de emitir). */
    public static function missingContractor(): array
    {
        $c = self::contractorFromSettings();
        $labels = [
            'legal_name'     => 'Razón social (Compañía de producción)',
            'rfc'            => 'RFC',
            'address'        => 'Domicilio',
            'representative' => 'Representante legal',
            'email'          => 'Correo del contratante',
        ];
        $missing = [];
        foreach ($c as $k => $v) {
            if ($v === '') {
                $missing[] = $labels[$k];
            }
        }
        return $missing;
    }

    /** Contratante EFECTIVO: congelado si el contrato ya se emitió; si no, de settings. */
    private static function contractorFor(PayeeContract $c): array
    {
        if ($c->isEmitted()) {
            return [
                'legal_name'     => (string) $c->contractor_legal_name,
                'rfc'            => (string) $c->contractor_rfc,
                'address'        => (string) $c->contractor_address,
                'representative' => (string) $c->contractor_representative,
                'email'          => (string) $c->contractor_email,
            ];
        }
        return self::contractorFromSettings();
    }

    /**
     * Renglones etiqueta/valor de la carátula (ya filtrados: los vacíos NO entran).
     * Cada renglón: ['label_es', 'label_en', 'value'].
     */
    public static function rows(PayeeContract $c, string $language): array
    {
        $contractor = self::contractorFor($c);
        $dept  = $c->department;
        $deptName   = $dept ? $dept->name : null;
        $deptNameEn = $dept ? ($dept->name_en ?: $dept->name) : null;

        $fee = null;
        if ($c->fee_amount !== null && (float) $c->fee_amount > 0) {
            $fee = trim(((string) ($c->fee_currency ?: 'MXN')) . ' ' . number_format((float) $c->fee_amount, 2))
                . ($c->frequencyLabel() ? ' · ' . $c->frequencyLabel() : '');
        }

        $yesNo = fn ($v, $lang) => $v === null ? null : ($lang === 'en' ? ($v ? 'Yes' : 'No') : ($v ? 'Sí' : 'No'));
        $date  = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;
        $money = fn ($v) => ($v !== null && (float) $v != 0.0) ? number_format((float) $v, 2) : null;

        // Definición: [label_es, label_en, value]. El valor null/'' se descarta luego.
        $defs = [
            // Contratante (la productora)
            ['Contratante (la Empresa)', 'Contracting Party (the Company)', $contractor['legal_name']],
            ['RFC', 'Tax ID (RFC)', $contractor['rfc']],
            ['Domicilio', 'Address', $contractor['address']],
            ['Representante', 'Representative', $contractor['representative']],
            ['Correo', 'Email', $contractor['email']],
            // Contratado (la persona)
            ['Contratado', 'Contractor', optional($c->payee)->name],
            ['Nombre en créditos', 'Screen credit', $c->credit_name],
            ['Actividad', 'Role', $c->crew_activity],
            ['Departamento', 'Department', $language === 'es' ? $deptName : $deptNameEn],
            // Fechas
            ['Fecha efectiva', 'Effective date', $date($c->effective_date)],
            ['Vigencia estimada', 'Estimated end', $date($c->estimated_end_date)],
            ['Vigencia definitiva', 'Definitive end', $date($c->definitive_end_date)],
            // Honorarios
            ['Honorarios', 'Fee', $fee],
            ['CFDI propio', 'Own invoice', $yesNo($c->issues_own_cfdi, $language)],
            // Sindicato
            ['Sindicato / nómina', 'Union / payroll', $c->union_payroll],
            ['Agremiado', 'Union member', $yesNo($c->union_is_member, $language)],
            ['Retención sindical', 'Union withholding', $c->union_retention_pct !== null ? rtrim(rtrim((string) $c->union_retention_pct, '0'), '.') . '%' : null],
            // Viáticos
            ['Viático desayuno', 'Per-diem breakfast', $money($c->perdiem_breakfast)],
            ['Viático comida', 'Per-diem lunch', $money($c->perdiem_lunch)],
            ['Viático cena', 'Per-diem dinner', $money($c->perdiem_dinner)],
            ['Viático semanal (prep/wrap)', 'Weekly per-diem (prep/wrap)', $money($c->perdiem_weekly_prep)],
            ['Viático semanal (shoot)', 'Weekly per-diem (shoot)', $money($c->perdiem_weekly_shoot)],
            // Hospedaje / vuelos / presupuesto
            ['Hospedaje', 'Lodging', $c->lodging_type],
            ['Complemento hospedaje mensual', 'Monthly lodging supplement', $money($c->lodging_monthly_supplement)],
            ['Vuelos redondos', 'Round-trip flights', $c->round_flights],
            ['Cuenta presupuestal', 'Budget account', $c->budget_account],
            // Beneficiario
            ['Beneficiario', 'Beneficiary', $c->beneficiary_name],
            ['Parentesco', 'Relationship', $c->beneficiary_relationship],
            ['Teléfono del beneficiario', 'Beneficiary phone', $c->beneficiary_phone],
        ];

        $rows = [];
        foreach ($defs as [$es, $en, $value]) {
            $v = is_string($value) ? trim($value) : $value;
            if ($v === null || $v === '') {
                continue;   // un campo vacío NO imprime su renglón
            }
            $rows[] = ['label_es' => $es, 'label_en' => $en, 'value' => (string) $v];
        }
        return $rows;
    }

    /** Logo del cliente como data-URI (base64). NUNCA cae al logo de CrewCare: vacío => sin logo. */
    public static function logoDataUri(): ?string
    {
        $logo = trim((string) Branding::get('client_logo', ''));
        if ($logo === '') {
            return null;
        }
        $rel = ltrim(str_replace('/storage/', '', $logo), '/');
        if (! Storage::disk('public')->exists($rel)) {
            return null;
        }
        $ext  = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $mime = $ext === 'svg' ? 'image/svg+xml'
            : ($ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));
        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($rel));
    }

    /** Genera el PDF de la carátula (bytes) con dompdf, en el idioma dado (o el del contrato). */
    public static function render(PayeeContract $c, ?string $language = null): string
    {
        $language = $language ?: ($c->language ?: ContractClause::LANG_ES);
        return Pdf::loadView('contracts.caratula', [
            'rows'     => self::rows($c, $language),
            'language' => $language,
            'logo'     => self::logoDataUri(),
            'contract' => $c,
        ])->setPaper('a4', 'portrait')->output();
    }
}
