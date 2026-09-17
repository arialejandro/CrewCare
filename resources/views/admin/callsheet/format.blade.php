@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

@php $logi = ['hotel', 'pickup', 'place', 'call', 'out']; @endphp

<style>
    /* Selector VISUAL del formato del back: tarjetas con un mini-back (se ve el layout, no los nombres). */
    .fmt-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; }
    .fmt-card { display:block; cursor:pointer; margin:0; }
    .fmt-card input { position:absolute; opacity:0; width:0; height:0; }
    .fmt-card__inner { border:1.5px solid var(--stroke); border-radius:14px; overflow:hidden; background:var(--surface-2);
        transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
    .fmt-card:hover .fmt-card__inner { border-color:color-mix(in srgb, var(--brand-primary) 45%, var(--stroke)); transform:translateY(-2px); }
    .fmt-card input:checked + .fmt-card__inner { border-color:var(--brand-primary); box-shadow:0 0 0 3px rgba(var(--brand-primary-rgb), .18); }

    /* Los elementos del mini van en <span> (dentro de <label>): forzar display de caja. */
    .fmt-card__inner, .fmt-card__meta, .fmt-mini, .fmt-mini__hd, .fmt-mini__safe,
    .fmt-mini__band, .fmt-mini__row, .fmt-mini__meal, .fmt-mini__meal .mh, .fmt-mini__meal .mr,
    .fmt-mini__notes, .fmt-mini__notes span { display:block; }
    .fmt-mini__cols, .fmt-mini__chead { display:flex; }
    .fmt-mini__col { display:flex; flex-direction:column; }
    .fmt-mini__chead .cell { flex:0 0 auto; box-sizing:border-box; }

    /* Mini-back = hoja de papel (siempre claro, como impreso). */
    .fmt-mini { background:#fff; padding:7px 7px 6px; }
    .fmt-mini__hd { text-align:center; font-size:7px; font-weight:700; letter-spacing:.05em; color:#222; text-transform:uppercase; border-bottom:1px solid #111; padding-bottom:2px; margin-bottom:3px; }
    .fmt-mini__safe { height:4px; background:#fbeeee; border:.5px solid #d99; border-radius:1px; margin-bottom:3px; }
    .fmt-mini__cols { display:flex; gap:3px; }
    .fmt-mini__col { flex:1; min-width:0; display:flex; flex-direction:column; }
    .fmt-mini__chead { display:flex; gap:0; margin-bottom:1.5px; }
    .fmt-mini__chead .cell { height:6px; border:.5px solid #b9b9b9; background:#e2e2e2; }
    .fmt-mini__chead .cell--t { background:#fbfbe6; }
    .fmt-mini__band { height:5px; background:#c2c2c2; border:.5px solid #9a9a9a; margin-bottom:1px; }
    .fmt-mini__row { height:3.5px; border-bottom:.5px solid #e2e2e2; }
    .fmt-mini__meal { margin-top:3px; border:.5px solid #999; }
    .fmt-mini__meal .mh { height:5px; background:#c2c2c2; }
    .fmt-mini__meal .mr { height:3.5px; border-top:.5px solid #cfcfcf; }
    .fmt-mini__notes { margin-top:4px; }
    .fmt-mini__notes span { display:block; height:2.5px; background:#e6e6e6; border-radius:1px; margin-top:2px; }

    .fmt-card__meta { padding:.6rem .75rem .75rem; }
    .fmt-card__title { font-weight:700; color:var(--text); font-size:.95rem; display:flex; align-items:center; gap:.4rem; }
    .fmt-card__lang { font-size:.62rem; font-weight:800; letter-spacing:.04em; padding:.05rem .35rem; border-radius:5px;
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent); color:var(--brand-primary-dark, var(--brand-primary)); border:1px solid color-mix(in srgb, var(--brand-primary) 30%, var(--stroke)); }
    .fmt-card__chips { display:flex; flex-wrap:wrap; gap:.28rem; margin-top:.45rem; }
    .fmt-chip { font-size:.68rem; font-weight:600; padding:.1rem .45rem; border-radius:999px; background:var(--surface-3); color:var(--text); border:1px solid var(--stroke); }
    .fmt-chip--meal { background:transparent; color:var(--text-muted); border-style:dashed; }
    .fmt-card__cur { margin-top:.5rem; font-size:.72rem; font-weight:700; color:var(--brand-primary); display:none; align-items:center; gap:.25rem; }
    .fmt-card input:checked ~ .fmt-card__meta .fmt-card__cur { display:inline-flex; }

    /* Configuración global (papel + bandas): aplica sobre el preset elegido. */
    .fmt-global { display:flex; gap:2.5rem; flex-wrap:wrap; margin-top:1.5rem; padding:1rem 1.2rem;
        border:1px solid var(--stroke); border-radius:14px; background:var(--surface-2); }
    .fmt-global__label { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); font-weight:700; margin-bottom:.5rem; }
    .fmt-opts { display:flex; gap:.5rem; }
    .fmt-opt { cursor:pointer; margin:0; }
    .fmt-opt input { position:absolute; opacity:0; width:0; height:0; }
    .fmt-opt__box { display:inline-flex; align-items:center; gap:.5rem; padding:.4rem .75rem; border:1.5px solid var(--stroke);
        border-radius:10px; color:var(--text); font-weight:600; font-size:.88rem; background:var(--glass); transition:border-color .15s ease, box-shadow .15s ease; }
    .fmt-opt:hover .fmt-opt__box { border-color:color-mix(in srgb, var(--brand-primary) 45%, var(--stroke)); }
    .fmt-opt input:checked + .fmt-opt__box { border-color:var(--brand-primary); box-shadow:0 0 0 2px rgba(var(--brand-primary-rgb), .16); }
    .fmt-opt input:focus-visible + .fmt-opt__box { outline:2px solid var(--brand-primary); outline-offset:2px; }
    .fmt-paper { display:inline-block; width:13px; background:#fff; border:1px solid #999; border-radius:1px; }
    .fmt-paper--legal { height:19px; }
    .fmt-paper--letter { height:14px; }
    .fmt-band { display:inline-block; width:17px; height:12px; border:1px solid #999; border-radius:2px; }
    .fmt-band--gray { background:#c2c2c2; }
    .fmt-band--black { background:#111; }

    /* Figuras de aprobación: una fila por rol (rol fijo + persona elegible). */
    .fmt-signers { display:grid; grid-template-columns:1fr; gap:.85rem; }
    .fmt-signers__head { margin-bottom:-.2rem; }
    .fmt-signers__sub { margin:.35rem 0 0; font-size:.82rem; color:var(--text-muted); max-width:62ch; }
    .fmt-signer { display:grid; grid-template-columns:minmax(150px, 210px) 1fr; align-items:center; gap:.75rem; }
    .fmt-signer__role { margin:0; font-weight:700; font-size:.9rem; color:var(--text); }
    @media (max-width: 575.98px) {
        .fmt-signer { grid-template-columns:1fr; gap:.3rem; }
    }
</style>

<div class="container py-4 cs-wrap" style="max-width:920px">
    <div class="adm-header mb-2">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'layout', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Formato del back</h1>
            <p class="adm-subtitle">Se elige una vez por producción. Toca la tarjeta cuyo layout se parezca a tu llamado.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('callsheet.format.save') }}" method="POST">
        @csrf
        <div class="fmt-grid">
            @foreach($cards as $key => $card)
                @php
                    $p = $card['preset']; $cols = $card['cols']; $en = ($p['lang'] ?? 'es') === 'en';
                    $fields = array_values(array_filter($cols, fn ($c) => in_array($c['key'], $logi)));
                    $mealTxt = [
                        'matrix' => $en ? 'Meal matrix' : 'Comidas: matriz',
                        'ready'  => $en ? 'Ready times' : 'Comidas: listo@',
                        'lunch'  => $en ? 'Lunch count' : 'Comidas: conteo',
                        'list'   => $en ? 'Meal list' : 'Comidas: lista',
                    ][$p['meals'] ?? 'matrix'] ?? '';
                @endphp
                <label class="fmt-card">
                    <input type="radio" name="preset" value="{{ $key }}" @checked($current === $key)>
                    <span class="fmt-card__inner">
                        {{-- Mini-back (esquemático) --}}
                        <span class="fmt-mini">
                            <span class="fmt-mini__hd">{{ $en ? 'Call Sheet' : 'Hoja de Llamado' }}</span>
                            <span class="fmt-mini__safe"></span>
                            <span class="fmt-mini__cols">
                                @for($ci = 0; $ci < 3; $ci++)
                                    <span class="fmt-mini__col">
                                        <span class="fmt-mini__chead">
                                            @foreach($cols as $c)
                                                <span class="cell {{ in_array($c['key'], $logi) ? 'cell--t' : '' }}" style="width:{{ $c['w'] }}%"></span>
                                            @endforeach
                                        </span>
                                        <span class="fmt-mini__band"></span>
                                        <span class="fmt-mini__row"></span><span class="fmt-mini__row"></span><span class="fmt-mini__row"></span>
                                        <span class="fmt-mini__band"></span>
                                        <span class="fmt-mini__row"></span><span class="fmt-mini__row"></span>
                                        @if($ci === 2)
                                            <span class="fmt-mini__meal">
                                                <span class="mh"></span><span class="mr"></span><span class="mr"></span><span class="mr"></span>
                                            </span>
                                        @endif
                                    </span>
                                @endfor
                            </span>
                            <span class="fmt-mini__notes"><span></span><span style="width:80%"></span></span>
                        </span>
                    </span>
                    <span class="fmt-card__meta">
                        <span class="fmt-card__title">
                            {{ $p['label'] }}
                            <span class="fmt-card__lang">{{ $en ? 'EN' : 'ES' }}</span>
                        </span>
                        <span class="fmt-card__chips">
                            @foreach($fields as $f)<span class="fmt-chip">{{ $f['label'] === '@' ? ($en ? 'Place' : 'Lugar') : $f['label'] }}</span>@endforeach
                            <span class="fmt-chip fmt-chip--meal">{{ $mealTxt }}</span>
                        </span>
                        <span class="fmt-card__cur">@include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null]) {{ $en ? 'Selected' : 'Seleccionado' }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        {{-- Configuración global: papel + color de banda (aplica sobre el preset elegido). --}}
        <div class="fmt-global">
            <div>
                <div class="fmt-global__label">Papel</div>
                <div class="fmt-opts">
                    <label class="fmt-opt">
                        <input type="radio" name="paper" value="legal" @checked($paper === 'legal')>
                        <span class="fmt-opt__box"><span class="fmt-paper fmt-paper--legal"></span>Oficio</span>
                    </label>
                    <label class="fmt-opt">
                        <input type="radio" name="paper" value="letter" @checked($paper === 'letter')>
                        <span class="fmt-opt__box"><span class="fmt-paper fmt-paper--letter"></span>Carta</span>
                    </label>
                </div>
            </div>
            <div>
                <div class="fmt-global__label">Bandas de departamento</div>
                <div class="fmt-opts">
                    <label class="fmt-opt">
                        <input type="radio" name="bands" value="gray" @checked($bands === 'gray')>
                        <span class="fmt-opt__box"><span class="fmt-band fmt-band--gray"></span>Gris</span>
                    </label>
                    <label class="fmt-opt">
                        <input type="radio" name="bands" value="black" @checked($bands === 'black')>
                        <span class="fmt-opt__box"><span class="fmt-band fmt-band--black"></span>Negro</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Quién aprueba: las 3 figuras que firman el paquete y salen al pie del back. --}}
        <div class="fmt-global fmt-signers">
            <div class="fmt-signers__head">
                <div class="fmt-global__label">Figuras que aprueban el back</div>
                <p class="fmt-signers__sub">Firman el paquete del llamado y salen al pie del PDF. Déjalo en automático para que tome a quien tenga el puesto en el llamado del día.</p>
            </div>
            @foreach($signerRoles as $key => $role)
                <div class="fmt-signer">
                    <label class="fmt-signer__role" for="sg-{{ $key }}">{{ $role['es'] }}</label>
                    <select id="sg-{{ $key }}" name="signers[{{ $key }}]" class="form-select js-typeahead">
                        <option value="">Automático{{ ($signerAuto[$key] ?? '') !== '' ? ' — hoy: ' . $signerAuto[$key] : ' — hoy no hay nadie con ese puesto' }}</option>
                        @foreach($crew as $p)
                            <option value="{{ $p['id'] }}" @selected((int) ($signerPicks[$key] ?? 0) === $p['id'])>{{ $p['name'] }}{{ $p['cargo'] !== '' ? ' · ' . $p['cargo'] : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end my-4">
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar formato
            </button>
        </div>
    </form>

    @include('componentes._typeahead')

    <p class="cs-note">El formato define las columnas de logística, el estilo de comidas y los bloques de notas del pie — sobre el mismo esqueleto tipo CASPER. Aplica a todos los backs de la producción.</p>
</div>
@endsection
