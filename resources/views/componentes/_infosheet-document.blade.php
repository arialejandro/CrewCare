{{-- HOJA DE INFORMACIÓN — el TRATO como DOCUMENTO (se revisa antes de autorizar/firmar, tipo DocuSign).
     Espeja la hoja real: identidad + domicilio + datos de producción + importes por fase + desglose +
     bancarios + beneficiario/emergencia + documentos + firmas. Recibe $contract; deriva el resto.
     Se pinta como "papel" claro (destaca sobre la ficha oscura y se lee como documento formal). --}}
@php
    $c = $contract;
    $p = $c->payee;
    $money = fn ($v) => ($v !== null && $v !== '' && (float) $v != 0.0) ? number_format((float) $v, 2) : '';
    $date  = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
    $curr  = $c->fee_currency ?: 'MXN';
    $phases = [
        'soft_prep' => __('Preparación'),
        'prep'      => __('Prep'),
        'shoot'     => __('Rodaje'),
        'wrap'      => __('Entrega / Post'),
    ];
    $base = (float) $c->fee_amount;
    $iva  = (float) $c->tax_iva;
    $risr = (float) $c->tax_isr_retention;
    $riva = (float) $c->tax_iva_retention;
    $totalPago = ($base + $iva) - $risr - $riva;
    $benef   = $p->beneficiaries->first();
    $status  = \App\Support\InfosheetSigning::statusFor($c);
    $regimes = $p->fiscalRegimes->map(fn ($r) => trim(($r->code ? $r->code . ' · ' : '') . ($r->name ?? '')))->filter()->all();
    // Firma DENTRO del documento (DocuSign): $signSlotLabel = el casillero "Autoriza" que ESTE usuario
    // firma ahora (o null en solo-lectura). El pad se dibuja en ESE bloque, no en un campo aparte.
    $signSlotLabel = $signSlotLabel ?? null;
    $adopted       = $adopted ?? null;
@endphp
@once
<style>
    .cc-hoja { background:#fff; color:#1f2a3a; border-radius:10px; padding:1.1rem 1.2rem; font-size:.82rem; line-height:1.45; box-shadow:0 2px 10px rgba(0,0,0,.28); }
    .cc-hoja__band { background:#26324a; color:#fff; font-weight:700; font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; padding:.35rem .6rem; border-radius:5px; margin:.95rem 0 .55rem; }
    .cc-hoja__title { text-align:center; background:#0f1115; font-size:.9rem; letter-spacing:.08em; margin-top:0; }
    .cc-hoja__title span { opacity:.7; font-weight:600; }
    .cc-hoja__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.35rem .9rem; }
    .cc-hoja__grid--money { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .cc-hoja__grid > div { display:flex; flex-direction:column; border-bottom:1px solid #eef1f5; padding-bottom:.22rem; }
    .cc-hoja__wide { grid-column:1 / -1; }
    .cc-hoja__grid span { color:#8a93a2; font-size:.66rem; text-transform:uppercase; letter-spacing:.03em; }
    .cc-hoja__grid b { color:#1f2a3a; font-weight:600; font-size:.85rem; word-break:break-word; }
    .cc-hoja__table { width:100%; border-collapse:collapse; font-size:.78rem; margin:.2rem 0 .5rem; }
    .cc-hoja__table th { background:#f6f8fa; color:#8a93a2; text-transform:uppercase; font-size:.64rem; padding:.35rem .5rem; text-align:left; border:1px solid #eef1f5; }
    .cc-hoja__table td { padding:.35rem .5rem; border:1px solid #eef1f5; }
    .cc-hoja__table .cc-hoja__total td { font-weight:800; background:#f6f8fa; }
    .cc-hoja__docs { display:flex; flex-wrap:wrap; gap:.4rem; }
    .cc-hoja__doc { background:#eef7ee; color:#1e6b34; border-radius:5px; padding:.2rem .5rem; font-size:.72rem; font-weight:600; }
    .cc-hoja__signs { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-top:.5rem; }
    .cc-hoja__sign { text-align:center; }
    .cc-hoja__sign-line { height:44px; border-bottom:1px solid #26324a; margin-bottom:.3rem; }
    .cc-hoja__sign-img { height:46px; object-fit:contain; display:block; margin:0 auto; border-bottom:1px solid #26324a; }
    .cc-hoja__sign b { display:block; font-size:.8rem; margin-top:.2rem; }
    .cc-hoja__sign span { color:#8a93a2; font-size:.68rem; }
    .cc-hoja__ok { color:#1e6b34; font-size:.66rem; font-weight:700; }
    .cc-hoja__note { margin-top:.8rem; font-size:.68rem; color:#8a93a2; font-style:italic; }
    /* FIRMAR AQUÍ: el pad va DENTRO del bloque "Autoriza" (no un campo aparte). Bloque a todo el ancho. */
    .cc-hoja__sign--active { grid-column:1 / -1; text-align:left; }
    .cc-hoja__signhint { display:block; font-size:.66rem; font-weight:700; color:#26324a; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.25rem; }
    /* El pad NO debe ocupar todo el ancho (se veía gigante): caja de firma acotada. */
    .cc-hoja__signpad { max-width:400px; }
    .cc-hoja__signpad .cc-sigpad__canvas { height:120px; }
    .cc-hoja__signpad .cc-sigpad__wrap { border:1px dashed #26324a; background:#fff; }
    .cc-hoja__signpad .cc-sigpad__savelbl { display:none; }
    .cc-hoja__signpad .cc-sigpad__tools { gap:.3rem; margin-top:.35rem; }
    .cc-hoja .btn-crew-soft { background:#f1f3f7; border:1px solid #d7dbe0; color:#26324a; }
    .cc-hoja .btn-crew-soft:hover, .cc-hoja .btn-crew-soft:focus { background:#e6e9ef; color:#26324a; }
    .cc-hoja .cc-sigpad__typed { background:#fff; border:1px solid #d7dbe0; color:#26324a; max-width:150px; }
    @media (max-width:640px){ .cc-hoja__grid, .cc-hoja__grid--money { grid-template-columns:1fr; } }
</style>
@endonce
<div class="cc-hoja">
    <div class="cc-hoja__band cc-hoja__title">{{ __('HOJA DE INFORMACIÓN') }} <span>({{ __('CREW') }})</span></div>

    <div class="cc-hoja__grid">
        <div><span>{{ __('Nombre / Razón social') }}</span><b>{{ $p->name ?: '—' }}</b></div>
        @if($p->legal_representative)<div><span>{{ __('Representante legal') }}</span><b>{{ $p->legal_representative }}</b></div>@endif
        <div><span>{{ __('Nacionalidad') }}</span><b>{{ $p->nationality === 'extranjera' ? __('Extranjera') : __('Mexicana') }}</b></div>
        <div><span>{{ __('R.F.C.') }}</span><b>{{ strtoupper((string) $p->rfc) ?: '—' }}</b></div>
    </div>

    <div class="cc-hoja__band">{{ __('Domicilio') }}</div>
    <div class="cc-hoja__grid">
        <div class="cc-hoja__wide"><span>{{ __('Domicilio') }}</span><b>{{ $p->fullAddress() ?: '—' }}</b></div>
        <div><span>{{ __('Teléfono') }}</span><b>{{ $p->phone ?: '—' }}</b></div>
        <div><span>{{ __('E-mail') }}</span><b>{{ $p->email ?: '—' }}</b></div>
    </div>

    <div class="cc-hoja__band">{{ __('Datos de producción') }}</div>
    <div class="cc-hoja__grid">
        <div><span>{{ __('Puesto') }}</span><b>{{ $c->title ?: '—' }}</b></div>
        <div><span>{{ __('Departamento') }}</span><b>{{ optional($c->department)->name ?: '—' }}</b></div>
        <div><span>{{ __('Nombre en créditos') }}</span><b>{{ $c->credit_name ?: $p->name }}</b></div>
        <div><span>{{ __('Régimen fiscal') }}</span><b>{{ count($regimes) ? implode(', ', $regimes) : '—' }}</b></div>
        <div class="cc-hoja__wide"><span>{{ __('Descripción de la actividad o entregable') }}</span><b>{{ $c->crew_activity ?: '—' }}</b></div>
        <div><span>{{ __('Comprobante') }}</span><b>{{ $c->payment_document_type ? ucfirst($c->payment_document_type) : '—' }}</b></div>
        <div><span>{{ __('Periodos de pago') }}</span><b>{{ $c->frequencyLabel() ?: '—' }}</b></div>
    </div>

    <div class="cc-hoja__band">{{ __('Importes') }}</div>
    <table class="cc-hoja__table">
        <thead><tr>
            <th>{{ __('Fase') }}</th><th>{{ __('Semanas') }}</th><th>{{ __('Importe / semana') }}</th><th>{{ __('Total') }}</th>
        </tr></thead>
        <tbody>
            @foreach($phases as $key => $label)
                @php $w = $c->{'fee_' . $key . '_weeks'}; $r = $c->{'fee_' . $key . '_rate'}; $a = $c->{'fee_' . $key . '_amount'}; @endphp
                @if($w || $r || $a)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $w ? rtrim(rtrim(number_format((float) $w, 2), '0'), '.') : '—' }}</td>
                        <td>{{ $money($r) ?: '—' }}</td>
                        <td>{{ $money($a) ?: '—' }}</td>
                    </tr>
                @endif
            @endforeach
            <tr class="cc-hoja__total">
                <td colspan="3">{{ __('Total contrato') }}</td>
                <td>{{ $curr }} {{ $money($base) ?: '0.00' }}</td>
            </tr>
        </tbody>
    </table>
    <div class="cc-hoja__grid">
        <div><span>{{ __('Inicia') }}</span><b>{{ $date($c->effective_date) ?: '—' }}</b></div>
        <div><span>{{ __('Termina (estimado)') }}</span><b>{{ $date($c->estimated_end_date) ?: '—' }}</b></div>
    </div>

    @if($iva || $risr || $riva)
        <div class="cc-hoja__band">{{ __('Desglose del pago') }}</div>
        <div class="cc-hoja__grid cc-hoja__grid--money">
            <div><span>{{ __('Subtotal') }}</span><b>{{ $money($base) ?: '0.00' }}</b></div>
            <div><span>{{ __('I.V.A.') }}</span><b>{{ $money($iva) ?: '0.00' }}</b></div>
            <div><span>{{ __('Retención ISR') }}</span><b>{{ $money($risr) ?: '0.00' }}</b></div>
            <div><span>{{ __('Retención IVA') }}</span><b>{{ $money($riva) ?: '0.00' }}</b></div>
            <div><span>{{ __('Total a pagar') }}</span><b>{{ $curr }} {{ $money($totalPago) ?: '0.00' }}</b></div>
        </div>
    @endif

    <div class="cc-hoja__band">{{ __('Datos bancarios') }}</div>
    <div class="cc-hoja__grid">
        <div><span>{{ __('Banco') }}</span><b>{{ $p->bank_name ?: '—' }}</b></div>
        <div><span>{{ __('Cuenta') }}</span><b>{{ $p->bank_account ?: '—' }}</b></div>
        <div><span>{{ __('CLABE') }}</span><b>{{ $p->bank_clabe ?: '—' }}</b></div>
        <div><span>{{ __('Partida del presupuesto') }}</span><b>{{ $c->budget_account ?: '—' }}</b></div>
        <div><span>{{ __('Administra caja chica') }}</span><b>{{ $c->manages_petty_cash ? __('Sí') : __('No') }}</b></div>
    </div>

    <div class="cc-hoja__band">{{ __('Beneficiario y contacto de emergencia') }}</div>
    <div class="cc-hoja__grid">
        <div><span>{{ __('Beneficiario mortis causa') }}</span><b>{{ $benef ? $benef->full_name : '—' }}</b></div>
        <div><span>{{ __('Teléfono') }}</span><b>{{ $benef ? ($benef->phone ?: '—') : '—' }}</b></div>
        <div><span>{{ __('En caso de accidente contactar a') }}</span><b>{{ $p->emergency_contact_name ?: '—' }}</b></div>
        <div><span>{{ __('Teléfono') }}</span><b>{{ $p->emergency_contact_phone ?: '—' }}</b></div>
    </div>

    @if($p->documents->isNotEmpty())
        <div class="cc-hoja__band">{{ __('Documentos que se adjuntan') }}</div>
        <div class="cc-hoja__docs">
            @foreach($p->documents as $doc)
                <span class="cc-hoja__doc">✓ {{ optional($doc->documentType)->name ?: $doc->document_type }}</span>
            @endforeach
        </div>
    @endif

    <div class="cc-hoja__band">{{ __('Firmas') }}</div>
    <div class="cc-hoja__signs">
        <div class="cc-hoja__sign">
            <div class="cc-hoja__sign-line"></div>
            <b>{{ $p->name }}</b>
            <span>{{ __('Contratado') }}@if($c->title) · {{ $c->title }}@endif</span>
        </div>
        @foreach($status as $st)
            @php $isMine = $signSlotLabel && $st['label'] === $signSlotLabel && empty($st['done']); @endphp
            <div class="cc-hoja__sign{{ $isMine ? ' cc-hoja__sign--active' : '' }}">
                @if(!empty($st['done']) && !empty($st['auth']) && $st['auth']->signature_image)
                    <img src="{{ $st['auth']->signature_image }}" alt="" class="cc-hoja__sign-img">
                    <b>{{ $st['auth']->name }}</b>
                    @if($st['auth']->verifyLatestSignature())<span class="cc-hoja__ok">✓ {{ __('Verificada') }}</span>@endif
                @elseif($isMine)
                    {{-- FIRMA AQUÍ: el pad va DENTRO de este bloque del documento. --}}
                    <span class="cc-hoja__signhint">{{ __('Firma aquí') }}</span>
                    <div class="cc-hoja__signpad">
                        @include('componentes._signature-pad', ['label' => null, 'adopted' => $adopted])
                    </div>
                @else
                    <div class="cc-hoja__sign-line"></div>
                    <b>&nbsp;</b>
                @endif
                <span>{{ __('Autoriza') }} · {{ $st['label'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="cc-hoja__note">{{ __('Las condiciones particulares de contratación se formalizarán a través del contrato respectivo.') }}</div>
</div>
