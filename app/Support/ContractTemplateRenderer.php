<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use Illuminate\Support\Carbon;

/**
 * CONTRACT BUILDER · FASE 1 — motor de render de la plantilla.
 *
 * Toma el `body` (HTML autorizado) y resuelve DOS marcadores:
 *   · `{{campo}}`        → valor del TRATO (escapado). Catálogo en fieldCatalog().
 *   · `[[firma:CLAVE]]`  → sello de firma en su lugar: autógrafa CONGELADA + su hash (verde=íntegra),
 *                          o "pendiente de firma" si aún no firma ese puesto.
 *
 * Las firmas se pasan como MAPA CLAVE→props (o null = pendiente): el motor NO conoce el sobre — quien
 * llama arma el mapa (para el preview con datos de ejemplo, o desde el sobre en la integración).
 * Los sellos van con estilos INLINE porque el documento final se imprime por Browsershot/dompdf,
 * donde los @push('styles') no existen. Emula el bloque _signature-block, autocontenido.
 */
class ContractTemplateRenderer
{
    /** `{{token}}` disponibles → etiqueta legible (para el menú del editor). */
    public static function fieldCatalog(): array
    {
        return [
            // ── Del CONTRATADO (la persona/entidad que cobra: su ficha de "quién cobra") ──
            'payee_nombre'                   => 'Nombre del contratado',
            'payee_rfc'                      => 'RFC del contratado',
            'domicilio_contratado'           => 'Domicilio del contratado',
            'tel_contratado'                 => 'Teléfono del contratado',
            'correo_contratado'              => 'Correo del contratado',
            'emergencia_contratado'          => 'Contacto de emergencia del contratado',
            'beneficiario_contratado'        => 'Beneficiario del contratado',
            'representante_legal_contratado' => 'Representante legal del contratado (moral)',
            'loanout_contratado'             => 'Empresa / loanout del contratado (moral)',
            'regimen_fiscal'                 => 'Régimen fiscal del contrato',
            'nacionalidad_contratado'        => 'Nacionalidad del contratado',
            'banco_contratado'               => 'Banco del contratado',
            'sucursal_contratado'            => 'Sucursal bancaria del contratado',
            'cuenta_contratado'              => 'Cuenta bancaria del contratado',
            'clabe_contratado'               => 'CLABE interbancaria del contratado',
            // ── Del TRATO ──
            'puesto'              => 'Puesto',
            'actividad'           => 'Actividad / entregable',
            'credito'             => 'Nombre en créditos',
            'honorarios'          => 'Honorarios (total)',
            'honorarios_letra'    => 'Honorarios con letra (00/100 M.N.)',
            'moneda'              => 'Moneda',
            'vigencia_inicio'     => 'Inicio de vigencia',
            'vigencia_fin'        => 'Fin de vigencia',
            'titulo_programa'     => 'Título del programa / producción',
            // ── De la EMPRESA contratante (la productora, desde la Marca) ──
            'empresa'             => 'Empresa contratante',
            'representante_legal' => 'Representante legal (empresa)',
            'domicilio_empresa'   => 'Domicilio de la empresa',
            'fecha_hoy'           => 'Fecha de hoy',
        ];
    }

    /**
     * Anclas de firma disponibles (para el menú del editor): CLAVE → etiqueta. Deriva de "Firmas
     * Prod." (la lista configurable de firmantes) + el contratado. Sin lista → sugerencias clásicas.
     */
    public static function anchorCatalog(): array
    {
        $out = ['contratado' => __('Contratado')];

        foreach (SignaturePositions::signerEntries() as $entry) {
            if ($entry === SignaturePositions::DEPT_HOD) {
                $out['dept_hod'] = __('HOD del departamento del contrato');
            } else {
                $out['puesto:' . (int) $entry] = SignaturePositions::positionLabel((int) $entry);
            }
        }

        if (count($out) === 1) {   // sin lista configurada → sugerencias útiles
            $out['line_producer'] = __('Productor en Línea');
            $out['dept_hod']      = __('HOD del departamento del contrato');
        }

        // Rúbrica: un ancla MÁS que el redactor coloca A MANO donde quiera (no se repite sola). Se
        // estampa con la inicial del contratado, en formato compacto. Va al final para no romper el
        // check de "sin lista configurada" de arriba.
        $out['rubrica'] = __('Rúbrica');

        return $out;
    }

    /** Valores del TRATO para llenar los `{{campos}}` (mismo origen que la carátula). */
    public static function valuesFor(PayeeContract $contract): array
    {
        $payee = $contract->payee;
        $fin   = $contract->definitive_end_date ?: $contract->estimated_end_date;
        $fmt   = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;

        // ── Datos del CONTRATADO (su ficha de "quién cobra"). El dato que NO se capturó cae a
        // cadena vacía (fill() la pinta en blanco), NUNCA a "[CONFIRMAR]". "En su caso" (loanout /
        // representante legal) solo aplica a persona MORAL: en física quedan en blanco. ──
        $isMoral = $payee && $payee->isMoral();

        // Emergencia: nombre + teléfono en una línea, sin dejar guion suelto.
        $emergName  = optional($payee)->emergency_contact_name;
        $emergPhone = optional($payee)->emergency_contact_phone;
        $emergencia = trim($emergName . ($emergName && $emergPhone ? ' — ' : '') . ($emergPhone ?: ''));

        // Beneficiario: el CONGELADO en el contrato (si ya se emitió) o el primero de la ficha.
        $firstBen   = $payee ? $payee->beneficiaries()->first() : null;
        $benName    = $contract->beneficiary_name ?: optional($firstBen)->full_name;
        $benRel     = $contract->beneficiary_relationship ?: optional($firstBen)->relationship;
        $beneficiario = trim((string) $benName . ($benName && $benRel ? ' (' . $benRel . ')' : ''));

        // Régimen fiscal: el elegido para ESTE contrato o, si no, el primero de la ficha.
        $firstReg   = $payee ? $payee->fiscalRegimes()->first() : null;
        $regimen    = optional($contract->fiscalRegime)->name ?: optional($firstReg)->name;

        return [
            'payee_nombre'                   => optional($payee)->name,
            'payee_rfc'                      => optional($payee)->rfc,
            'domicilio_contratado'           => $payee ? $payee->fullAddress() : null,
            'tel_contratado'                 => $payee ? $payee->contactPhone() : null,
            'correo_contratado'              => $payee ? $payee->contactEmail() : null,
            'emergencia_contratado'          => $emergencia,
            'beneficiario_contratado'        => $beneficiario,
            'representante_legal_contratado' => $isMoral ? $payee->legal_representative : null,
            'loanout_contratado'             => $isMoral ? $payee->name : null,
            'regimen_fiscal'                 => $regimen,
            'nacionalidad_contratado'        => optional($payee)->nationality,
            'banco_contratado'               => optional($payee)->bank_name,
            'sucursal_contratado'            => optional($payee)->bank_branch,
            'cuenta_contratado'              => optional($payee)->bank_account,
            'clabe_contratado'               => optional($payee)->bank_clabe,
            'puesto'              => $contract->title,
            'actividad'           => $contract->crew_activity,
            'credito'             => $contract->credit_name,
            'honorarios'          => $contract->fee_amount !== null ? '$' . number_format((float) $contract->fee_amount, 2) : null,
            'honorarios_letra'    => $contract->fee_amount !== null ? MoneyWords::pesos((float) $contract->fee_amount, $contract->fee_currency) : null,
            'moneda'              => $contract->fee_currency,
            'vigencia_inicio'     => $fmt($contract->effective_date),
            'vigencia_fin'        => $fmt($fin),
            'titulo_programa'     => optional($contract->production)->name,
            'empresa'             => Branding::get('company_name'),
            'representante_legal' => Branding::get('representante_legal'),
            'domicilio_empresa'   => Branding::get('office_address'),
            'fecha_hoy'           => now()->format('d/m/Y'),
        ];
    }

    /** Render completo: llena campos + estampa firmas. $sigMap: CLAVE → props|null (pendiente). */
    public static function render(ContractTemplate $template, array $values, array $sigMap = []): string
    {
        $body = self::fill((string) $template->body, $values);
        return self::stampAnchors($body, $sigMap);
    }

    /**
     * FASE 1c — arma el `$sigMap` con las firmas REALES del sobre: cada destinatario aporta su ancla
     * (anchor_key congelado) y, si YA firmó, su autógrafa CONGELADA + el hash de su sello (verde =
     * íntegra). Si no ha firmado, la clave queda null → el ancla se pinta "pendiente de firma".
     *
     * NO recalcula nada: cada firma ya está congelada por su propio sello (3.3). El documento es una
     * composición viva de firmas congeladas, tal como DocuSign.
     */
    public static function sigMapForEnvelope(ContractEnvelope $envelope): array
    {
        $map = ['__labels' => []];

        foreach ($envelope->recipients as $r) {
            $key = $r->anchor_key;
            if (! $key) {
                continue;   // destinatario sin ancla (ruta clásica) → no participa del estampado
            }
            $map['__labels'][$key] = $r->cargo ?: $r->roleLabel();

            if (! $r->isSigned()) {
                $map[$key] = null;   // pendiente
                continue;
            }
            $sig = $r->signatures()->latest('id')->first();
            $map[$key] = [
                'image'    => $r->signature_image,
                'signer'   => $r->name,
                'role'     => $r->cargo ?: $r->roleLabel(),
                'date'     => $r->signed_at,
                'hash'     => optional($sig)->document_hash,
                'verified' => $r->verifyLatestSignature(),
            ];
        }

        // La RÚBRICA es una marca DISTINTA de la firma del contratado (iniciales, una variante, o su
        // propia firma — la elige cada persona). Usa `rubrica_image` congelada; si no la capturó, cae a
        // la firma. Mismo estado (pendiente/firmada) que el contratado.
        if (array_key_exists('contratado', $map)) {
            $map['rubrica'] = $map['contratado'];
            $contractedRec = $envelope->recipients->firstWhere('anchor_key', 'contratado');
            if (is_array($map['rubrica']) && $contractedRec && $contractedRec->rubrica_image) {
                $map['rubrica']['image'] = $contractedRec->rubrica_image;
            }
            $map['__labels']['rubrica'] = __('Rúbrica');
        }

        return $map;
    }

    /**
     * Envuelve el documento renderizado en una hoja con estilos de contrato (serif, PDF-friendly).
     * $architecture (opcional) añade el CSS del FORMATO; $pageSize (opcional) el `@page` (tamaño +
     * margen) y el salto de página manual `.cc-pb`.
     *
     * NOTA (inc.3c-1 → 3c-2): la "rúbrica en cada página" NO se pinta aquí. En la impresión de Chrome
     * un `position:fixed` cae DENTRO del área de contenido (tapa el texto) y se repite en TODAS las
     * hojas —incluida la de Firmas, donde duplica la firma y parece un error—. No hay forma fiable de
     * anclarlo al margen físico ni de excluir la última hoja (comprobado con sonda). Las iniciales por
     * hoja se colocarán por COORDENADAS en inc.3c-2 (arrastre tipo DocuSign, eligiendo hoja y posición
     * y excluyendo la de Firmas). $rubrica se conserva por compatibilidad y por ahora se ignora.
     */
    public static function page(string $inner, ?string $architecture = null, ?string $pageSize = null, ?string $rubrica = null, ?string $fontFamily = null, ?string $fontSize = null): string
    {
        $extra = $architecture ? ContractArchitectures::pageCss($architecture) : '';
        // Siempre emitimos @page (tamaño + margen). El margen físico es EXACTAMENTE el de
        // ContractPageSizes — sin margen extra en `body` — para que el editor pueda calcular al PÍXEL
        // dónde cae el salto de página (una Carta/Oficio tiene alto útil fijo y conocido).
        $paged = ContractPageSizes::pageCss($pageSize ?: ContractPageSizes::DEFAULT);
        $font  = ContractFonts::stack($fontFamily);   // familia del sistema elegida por la plantilla
        $sz    = ContractFonts::sizePt($fontSize);    // tamaño base en pt (10/11/12)

        // ⚠ TIPOGRAFÍA CANÓNICA de impresión. Familia y tamaño salen de ContractFonts; el resto
        // (interlínea 1.6, h1/h2, td) DEBE coincidir EXACTO con `.cc-page` y el iframe medidor de
        // edit.blade (PRINT_CSS) — si cambias una medida aquí, cámbiala allá, o la guía de salto de
        // página del editor dejará de caer donde realmente cae.
        return '<!doctype html><meta charset="utf-8">'
            . '<style>' . $paged
            . 'body{font-family:' . $font . ';color:#1a1a1a;margin:0;line-height:1.15;font-size:' . $sz . '}'
            . 'h1{font-size:1.3rem;text-align:center}h2{font-size:1.02rem;border-bottom:1px solid #e2e2e2;padding-bottom:3px;margin-top:1.1rem}'
            . 'table{width:100%;border-collapse:collapse}td{padding:5px 7px;vertical-align:top}'
            . '.cc-signs{display:flex;flex-wrap:wrap;justify-content:space-around;align-items:flex-end;gap:24px 30px;margin:28px 0 8px}.cc-sign{flex:1 1 260px;max-width:48%;text-align:center}.cc-sign-anchor{min-height:88px}.cc-sign-role{font-size:11px;color:#555;margin-top:4px}'
            . $extra . '</style>' . $inner;
    }

    /** Reemplaza `{{token}}` por su valor (escapado). Token desconocido/vacío → cadena vacía. */
    public static function fill(string $body, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function ($m) use ($values) {
            $key = strtolower($m[1]);
            $val = $values[$key] ?? '';
            return e((string) $val);
        }, $body);
    }

    /**
     * Reemplaza `[[firma:CLAVE]]` por el sello (autógrafa+hash) o el placeholder de pendiente.
     * La rúbrica admite un desplazamiento LIBRE `[[firma:rubrica|dx,dy]]` (px): sigue anclada en el
     * flujo —así cae sola en su página al paginar— pero se mueve visualmente con `transform:translate`
     * (no afecta el layout ni la paginación). Es lo que persiste el arrastre del editor.
     */
    public static function stampAnchors(string $body, array $sigMap): string
    {
        return preg_replace_callback('/\[\[firma:([a-z0-9_:\-]+)(?:\|(-?\d+),(-?\d+))?\]\]/i', function ($m) use ($sigMap) {
            $key  = strtolower($m[1]);
            $data = $sigMap[$key] ?? null;
            if ($key === 'rubrica') {   // ancla compacta: inicial que el redactor coloca (y mueve) a mano
                $stamp = is_array($data) ? self::rubricaStamp($data) : self::rubricaPending();
                $dx = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
                $dy = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
                if ($dx !== 0 || $dy !== 0) {
                    $stamp = '<span style="display:inline-block;transform:translate(' . $dx . 'px,' . $dy . 'px)">' . $stamp . '</span>';
                }
                return $stamp;
            }
            return is_array($data) ? self::signatureStamp($data) : self::pendingStamp($sigMap['__labels'][$key] ?? $key, $key);
        }, $body);
    }

    /**
     * Sello de firma APLICADA — formato tipo DocuSign, TRANSPARENTE (no tapa el contenido de abajo):
     * corchete de color + “Firmado por:” + autógrafa congelada + línea + hash COMPLETO (verde=íntegra,
     * rojo=alterada). Estilos inline, PDF-safe. La autógrafa lleva `mix-blend-mode:multiply` para que
     * incluso un PNG con fondo blanco deje ver lo que tiene debajo.
     */
    private static function signatureStamp(array $p): string
    {
        $img    = $p['image']  ?? null;
        $signer = e((string) ($p['signer'] ?? ''));
        $hash   = $p['hash'] ? e((string) $p['hash']) : '';            // hash COMPLETO (transparencia: el doc se autocertifica)
        $ok     = ($p['verified'] ?? null) === true;
        $bad    = ($p['verified'] ?? null) === false;
        $accent = $bad ? '#b91c1c' : '#4b53d6';                        // corchete: índigo, rojo si alterada
        $tick   = $ok ? '✓ ' : ($bad ? '⚠ ' : '');
        $tcolor = $ok ? '#15803d' : ($bad ? '#b91c1c' : '#8a93a2');

        $mark = $img
            ? '<img src="' . e($img) . '" alt="Firma" style="max-width:170px;max-height:34px;display:inline-block;background:transparent;mix-blend-mode:multiply;">'
            : '<span style="display:inline-block;height:22px;"></span>';

        // Formato DocuSign: el corchete envuelve "Firmado por:" + autógrafa + el HASH (donde DocuSign
        // pone su id, pero COMPLETO); debajo, línea + NOMBRE. El rol va fuera (en `.cc-sign-role`).
        return '<span class="cc-sig-stamp" style="display:block;text-align:center;background:transparent;">'
            . '<span style="display:inline-flex;align-items:stretch;gap:5px;text-align:left;">'
                . '<span style="flex:0 0 auto;width:6px;border:1.25px solid ' . $accent . ';border-right:0;border-radius:4px 0 0 4px;"></span>'
                . '<span style="flex:1 1 auto;">'
                    . '<span style="display:block;font-size:7px;color:#6b7482;letter-spacing:.3px;">' . e(__('Firmado por:')) . '</span>'
                    . $mark
                    . ($hash ? '<span style="display:block;font-size:6px;line-height:1.3;color:' . $tcolor . ';word-break:break-all;">' . $tick . '<code style="font-size:6px;color:#5b6472;">' . $hash . '</code></span>' : '')
                . '</span>'
            . '</span>'
            . '<span style="display:block;border-top:1px solid #333;margin:1px auto 2px;max-width:220px;"></span>'
            . '<span style="display:block;font-weight:700;font-size:9.5px;color:#10151f;line-height:1.2;">' . $signer . '</span>'
            . '</span>';
    }

    /**
     * Placeholder de firma PENDIENTE (aún no firma ese puesto). El `data-anchor` (clave del ancla) es
     * inerte en el PDF/impresión, pero deja que la CEREMONIA de firma (el iframe del contrato HTML)
     * identifique qué recuadro le toca a cada destinatario y lo vuelva clicable.
     */
    private static function pendingStamp(string $label, string $key = ''): string
    {
        $attr = $key !== '' ? ' data-anchor="' . e($key) . '"' : '';
        return '<span class="cc-sig-pending"' . $attr . ' style="display:inline-block;border:1px dashed #c3c9d4;border-radius:8px;'
            . 'padding:10px 12px;min-width:150px;min-height:44px;text-align:center;color:#8a93a2;font-style:italic;font-size:11px;'
            . 'vertical-align:bottom;background:transparent;">Pendiente de firma<br><span style="font-style:normal;font-size:9.5px;">' . e($label) . '</span></span>';
    }

    /**
     * RÚBRICA — versión COMPACTA de la firma del contratado (inicial). Es un ancla que el redactor
     * coloca A MANO donde quiera (pie de hoja, al margen…); no se repite sola. Transparente, con un
     * corchete mínimo tipo DocuSign. NO carga el hash completo (el sello autoritativo va en el bloque
     * de Firmas); solo un ✓/⚠ de integridad.
     */
    private static function rubricaStamp(array $p): string
    {
        $img    = $p['image'] ?? null;
        $ok     = ($p['verified'] ?? null) === true;
        $bad    = ($p['verified'] ?? null) === false;
        $accent = $bad ? '#b91c1c' : '#4b53d6';
        $tick   = $ok ? ' ✓' : ($bad ? ' ⚠' : '');

        $mark = $img
            ? '<img src="' . e($img) . '" alt="Rúbrica" style="max-width:120px;max-height:34px;display:block;background:transparent;mix-blend-mode:multiply;">'
            : '<span style="display:block;height:22px;"></span>';

        return '<span class="cc-rubrica-stamp" style="display:inline-flex;align-items:stretch;gap:5px;'
            . 'vertical-align:bottom;background:transparent;">'
            . '<span style="flex:0 0 auto;width:6px;border:1.5px solid ' . $accent . ';border-right:0;border-radius:4px 0 0 4px;"></span>'
            . '<span style="flex:1 1 auto;display:block;text-align:left;">'
                . $mark
                . '<span style="display:block;border-top:1px solid #b6bcc7;margin-top:1px;"></span>'
                . '<span style="display:block;font-size:7pt;color:#8a93a2;">' . e(__('Rúbrica')) . $tick . '</span>'
            . '</span></span>';
    }

    /** Placeholder compacto de rúbrica PENDIENTE (aún no firma el contratado). */
    private static function rubricaPending(): string
    {
        return '<span class="cc-rubrica-pending" data-anchor="rubrica" style="display:inline-block;border:1px dashed #c3c9d4;border-radius:6px;'
            . 'padding:4px 9px;font-size:7.5pt;color:#8a93a2;font-style:italic;vertical-align:bottom;background:transparent;">'
            . 'Rúbrica pendiente</span>';
    }
}
