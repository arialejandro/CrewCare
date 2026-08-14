{{--
    _signature-block.blade.php — FIRMA AUTÓGRAFA · render tipo DocuSign: la firma dibujada + su HASH
    de integridad al lado (verificada e íntegra / alterada), nombre y fecha. SOLO LECTURA.

    Props (todos opcionales; sin imagen = "pendiente de firma"):
      - $image     data URL PNG de la firma aplicada.
      - $signer    nombre de quien firmó.
      - $role      rótulo del papel (p. ej. "Productor en línea").
      - $date      fecha/hora de la firma.
      - $hash      document_hash del sello (se muestra recortado al lado).
      - $verified  true = íntegra, false = alterada, null = sin verificar.
--}}
@php
    $sImage    = $image ?? null;
    $sSigner   = $signer ?? '';
    $sRole     = $role ?? null;
    $sDate     = $date ?? null;
    $sHash     = $hash ?? null;
    $sVerified = $verified ?? null;
@endphp
<div class="cc-sigblock">
    <div class="cc-sigblock__mark">
        @if($sImage)
            <img src="{{ $sImage }}" alt="{{ __('Firma autógrafa') }}" class="cc-sigblock__img">
        @else
            <span class="cc-sigblock__pending">{{ __('Pendiente de firma') }}</span>
        @endif
    </div>
    <div class="cc-sigblock__meta">
        @if($sSigner)<div class="cc-sigblock__name">{{ $sSigner }}</div>@endif
        @if($sRole)<div class="cc-sigblock__role">{{ $sRole }}</div>@endif
        @if($sDate)<div class="cc-sigblock__date">{{ \Illuminate\Support\Carbon::parse($sDate)->format('d/m/Y H:i') }}</div>@endif
        @if($sHash)
            <div class="cc-sigblock__hash {{ $sVerified === false ? 'is-bad' : ($sVerified === true ? 'is-ok' : '') }}">
                @if($sVerified === true)
                    @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-14', 'label' => null])
                    <span>{{ __('Verificada e íntegra') }}</span>
                @elseif($sVerified === false)
                    @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-14', 'label' => null])
                    <span>{{ __('Alterada') }}</span>
                @else
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                @endif
                <code>{{ substr($sHash, 0, 20) }}…</code>
            </div>
        @endif
    </div>
</div>
@once
@push('styles')
<style>
.cc-sigblock { display: flex; gap: 1rem; align-items: center; padding: .75rem 1rem; border: 1px solid var(--border, #d7dce4); border-radius: 12px; background: var(--surface-2, #fafbfc); }
.cc-sigblock__mark { flex: 0 0 auto; width: 180px; height: 72px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #c3c9d4; }
.cc-sigblock__img { max-width: 100%; max-height: 72px; }
.cc-sigblock__pending { font-size: .78rem; color: var(--text-muted, #8a93a2); font-style: italic; }
.cc-sigblock__name { font-weight: 700; font-size: .95rem; color: var(--text, #10151f); }
.cc-sigblock__role { font-size: .78rem; color: var(--text-muted, #6b7482); }
.cc-sigblock__date { font-size: .78rem; color: var(--text-muted, #6b7482); }
.cc-sigblock__hash { display: inline-flex; align-items: center; gap: .35rem; margin-top: .25rem; font-size: .72rem; color: var(--text-muted, #6b7482); }
.cc-sigblock__hash.is-ok { color: #15803d; }
.cc-sigblock__hash.is-bad { color: #b91c1c; }
.cc-sigblock__hash code { font-size: .72rem; }
</style>
@endpush
@endonce
