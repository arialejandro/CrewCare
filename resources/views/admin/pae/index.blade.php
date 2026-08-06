@extends('layouts.app')

{{-- ============================================================================
     LISTADO de PAE emitidos (Planes de Atención a Emergencias). Cada fila abre el
     documento sellado. Emitir uno nuevo desde el botón de arriba.
============================================================================ --}}

@section('content')
@php $en = app()->getLocale() === 'en'; @endphp

<div class="cc-pae container-fluid" style="max-width:980px;margin:0 auto;padding:18px 14px 60px;">

    <div class="pae-head">
        <div>
            <div class="pae-eyebrow">@include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico-18']) PAE · {{ $en ? 'Emergency Action Plans' : 'Planes de Atención a Emergencias' }}</div>
            <h1 class="pae-title">{{ $en ? 'Emergency action plans' : 'Planes de atención a emergencias' }}</h1>
            <div class="pae-sub cc-muted">{{ $en ? 'One per call sheet. Sealed and verifiable.' : 'Uno por llamado. Sellados y verificables.' }}</div>
        </div>
        <a class="btn btn-primary cc-cta" href="{{ route('pae.create') }}">
            @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18']) {{ $en ? 'Issue PAE' : 'Emitir PAE' }}
        </a>
    </div>

    @if(session('success'))
        <div class="pae-alert ok">@include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-16']) {{ session('success') }}</div>
    @endif

    @if($plans->isEmpty())
        <div class="cc-form-card" style="text-align:center;padding:34px 18px;">
            <div class="cc-muted" style="margin-bottom:12px;">{{ $en ? 'No emergency action plans issued yet.' : 'Aún no hay planes de atención a emergencias emitidos.' }}</div>
            <a class="btn btn-primary cc-cta" href="{{ route('pae.create') }}">{{ $en ? 'Issue the first one' : 'Emitir el primero' }}</a>
        </div>
    @else
        <div class="cc-form-card">
            <div class="pae-rows">
                @foreach($plans as $pl)
                    <a class="pae-row-item" href="{{ route('pae.show', $pl->uuid) }}">
                        <span class="pae-folio">{{ $pl->folio() }}</span>
                        <span class="pae-label">{{ $pl->plan_label ?: ($pl->shoot_day ? ('Día ' . $pl->shoot_day) : 'PAE') }}</span>
                        <span class="cc-muted pae-when">{{ optional($pl->issued_at)->format('d/m/Y H:i') }}</span>
                        <span class="pae-by">{{ $pl->issued_by_name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .cc-pae .pae-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
    .cc-pae .pae-eyebrow{display:inline-flex;align-items:center;gap:7px;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--brand-primary,#ff9900);}
    .cc-pae .pae-title{margin:.25rem 0 .1rem;font-size:1.5rem;font-weight:800;line-height:1.15;}
    .cc-pae .pae-sub{font-size:.95rem;}
    .cc-pae .pae-alert{padding:11px 13px;border-radius:11px;margin-bottom:14px;font-size:.92rem;display:flex;align-items:center;gap:8px;}
    .cc-pae .pae-alert.ok{background:rgba(16,185,129,.10);border:1px solid rgba(16,185,129,.35);}
    .cc-pae .pae-rows{display:flex;flex-direction:column;gap:6px;}
    .cc-pae .pae-row-item{display:flex;gap:14px;align-items:center;padding:10px 12px;border:1px solid var(--stroke,rgba(0,0,0,.08));border-radius:10px;text-decoration:none;color:inherit;}
    .cc-pae .pae-row-item:hover{border-color:var(--brand-primary,#ff9900);}
    .cc-pae .pae-folio{font-weight:800;font-family:var(--mono,monospace);flex:none;}
    .cc-pae .pae-label{font-weight:600;}
    .cc-pae .pae-when{margin-left:auto;font-size:.85rem;}
    .cc-pae .pae-by{font-size:.85rem;color:var(--muted,#6b7280);}
    @media (max-width:620px){
        .cc-pae .pae-row-item{flex-wrap:wrap;}
        .cc-pae .pae-when{margin-left:0;}
    }
</style>
@endpush
