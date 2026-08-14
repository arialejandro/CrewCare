{{-- EL INFOSHEET · FASE 2 — tarjeta en /profile (vista de la PERSONA). Muestra la mitad PERSONAL
     (qué falta del intake, con enlace a su asistente) y la mitad del TRATO (solo lectura: lo que
     producción capturó y que la persona firmará). Sangre/alergias NO viven aquí (silo clínico):
     se empuja al cuestionario de salud. Espera: $user, $payee, $intakeSteps, $intakeUrl, $deal. --}}
@php
    $labels  = \App\Support\IntakeProgress::LABELS;
    $missing = collect($intakeSteps ?? [])->filter(fn ($done) => ! $done)->keys();
@endphp
<div class="cc-form-card">
    <div class="cc-form-card__head">
        <span class="cc-form-ico">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-20', 'label' => null])</span>
        <div class="cc-form-card__titles">
            <h2 class="cc-form-card__title">{{ __('Hoja de información') }}</h2>
            <p class="cc-form-card__sub">{{ __('Tus datos y las condiciones de tu contratación.') }}</p>
        </div>
    </div>
    <div class="cc-form-card__body">

        {{-- ── Mitad PERSONAL: qué falta del intake ── --}}
        <div class="text-uppercase text-muted small fw-semibold mb-2" style="letter-spacing:.06em">{{ __('Tus datos') }}</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($labels as $key => $label)
                @if(($intakeSteps[$key] ?? false))
                    <span class="cc-chip cc-chip--ok">{{ __($label) }}</span>
                @else
                    <span class="cc-chip cc-chip--danger">{{ __($label) }} · {{ __('falta') }}</span>
                @endif
            @endforeach
        </div>
        <a class="btn btn-primary cc-cta" href="{{ $intakeUrl }}">
            @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico-18', 'label' => null])
            {{ $missing->isEmpty() ? __('Revisar mis datos') : __('Completar mis datos') }}
        </a>

        {{-- Nudge: sangre/alergias viven en el expediente clínico, no en el Infosheet. --}}
        @unless(auth()->user()->encuestadiaria)
            <div class="cc-help mt-2">
                @include('componentes._icon', ['name' => 'info', 'class' => 'cc-ico-14', 'label' => null])
                {{ __('Tu tipo de sangre y alergias se registran en el cuestionario de salud.') }}
                <a href="/dailyreport">{{ __('Responderlo') }}</a>
            </div>
        @endunless

        <hr class="my-3">

        {{-- ── Mitad del TRATO: solo lectura (lo que producción capturó = lo que firmarás) ── --}}
        <div class="text-uppercase text-muted small fw-semibold mb-2" style="letter-spacing:.06em">{{ __('Tu contratación') }}</div>
        @if($deal && ($deal->title || $deal->crew_activity || $deal->fee_amount))
            <div class="cc-info">
                @if($deal->title)<div class="cc-info-item"><span class="cc-info-lbl">{{ __('Puesto') }}</span><span class="cc-info-val">{{ $deal->title }}</span></div>@endif
                @if($deal->department)<div class="cc-info-item"><span class="cc-info-lbl">{{ __('Departamento') }}</span><span class="cc-info-val">{{ $deal->department->name }}</span></div>@endif
                @if($deal->crew_activity)<div class="cc-info-item cc-info-item--wide"><span class="cc-info-lbl">{{ __('Actividad') }}</span><span class="cc-info-val">{{ $deal->crew_activity }}</span></div>@endif
                @if($deal->credit_name)<div class="cc-info-item"><span class="cc-info-lbl">{{ __('Nombre en créditos') }}</span><span class="cc-info-val">{{ $deal->credit_name }}</span></div>@endif
                @if($deal->fee_amount)<div class="cc-info-item"><span class="cc-info-lbl">{{ __('Honorarios (total)') }}</span><span class="cc-info-val">${{ number_format((float) $deal->fee_amount, 2) }} {{ $deal->fee_currency }}</span></div>@endif
                @if($deal->workDates->isNotEmpty())<div class="cc-info-item"><span class="cc-info-lbl">{{ __('Días de trabajo') }}</span><span class="cc-info-val">{{ $deal->workDates->count() }}</span></div>@endif
            </div>
            {{-- Aviso de firma: si hay un sobre enviado y es MI turno, enlace directo (interno
                 logueado = su propio destinatario → salta el segundo factor). --}}
            @php
                $signRec = null;
                $env = $deal->envelopes()->where('status', \App\Models\ContractEnvelope::STATUS_SENT)->latest('id')->first();
                if ($env && $env->current_recipient_id) {
                    $cur = $env->currentRecipient;
                    if ($cur && (int) $cur->user_id === (int) auth()->id() && ! $cur->isSigned()) {
                        $signRec = $cur;
                    }
                }
            @endphp
            @if($signRec)
                <a class="btn btn-crew cc-cta mt-2" href="{{ \App\Http\Controllers\ContractSignController::signUrl($signRec) }}">
                    @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('Firmar mi contrato') }}
                </a>
            @else
                <p class="cc-help mt-2">{{ __('Esto es lo que firmarás cuando tu contrato esté listo.') }}</p>
            @endif
        @else
            <p class="text-muted mb-0">{{ __('Producción aún no ha capturado las condiciones de tu contratación.') }}</p>
        @endif
    </div>
</div>
