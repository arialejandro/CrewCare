@extends('layouts.app')

{{-- ============================================================================
     LISTADO de PAE emitidos (Planes de Atención a Emergencias). Cada fila abre el
     documento sellado. Emitir uno nuevo desde el botón de arriba.
============================================================================ --}}

@section('content')
@include('componentes._form-kit')

@php $en = app()->getLocale() === 'en'; @endphp

<div class="cc-pae container-fluid" style="max-width:1000px;margin:0 auto;padding:18px 14px 60px;">

    <div class="cc-page-head">
        <div>
            <h1 class="cc-h1">{{ $en ? 'Emergency action plans' : 'Planes de atención a emergencias' }}</h1>
            <p class="cc-sub">{{ $en ? 'One per call sheet. Sealed and verifiable.' : 'Uno por llamado. Sellados y verificables.' }}</p>
        </div>
        <a class="btn btn-primary cc-cta" href="{{ route('pae.create') }}">
            @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-16']) {{ $en ? 'Issue PAE' : 'Emitir PAE' }}
        </a>
    </div>

    @if(session('success'))
        <div class="cc-alert cc-alert--ok">{{ session('success') }}</div>
    @endif

    @if($plans->isEmpty())
        <div class="cc-form-card cc-empty">
            <div class="cc-empty-txt">{{ $en ? 'No emergency action plans issued yet.' : 'Aún no hay planes de atención a emergencias emitidos.' }}</div>
            <a class="btn btn-primary cc-cta" href="{{ route('pae.create') }}">{{ $en ? 'Issue the first one' : 'Emitir el primero' }}</a>
        </div>
    @else
        <div class="cc-form-card" style="padding:6px;">
            <div class="pae-rows">
                @foreach($plans as $pl)
                    <a class="pae-row" href="{{ route('pae.show', $pl->uuid) }}">
                        <span class="pae-folio">{{ $pl->folio() }}</span>
                        <span class="pae-ver">{{ $pl->versionLabel() }}</span>
                        <span class="pae-label">{{ $pl->plan_label ?: ($pl->shoot_day ? ('Día ' . $pl->shoot_day) : 'PAE') }}</span>
                        <span class="pae-when">{{ optional($pl->issued_at)->format('d/m/Y H:i') }}</span>
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
    .cc-pae .cc-page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px;}
    .cc-pae .cc-h1{margin:0;font-size:1.4rem;font-weight:800;line-height:1.2;}
    .cc-pae .cc-sub{margin:.25rem 0 0;font-size:.92rem;color:var(--muted,#6b7280);}
    .cc-pae .cc-alert{padding:11px 13px;border-radius:11px;margin-bottom:14px;font-size:.92rem;}
    .cc-pae .cc-alert--ok{background:rgba(16,185,129,.10);border:1px solid rgba(16,185,129,.35);}
    .cc-pae .cc-empty{text-align:center;padding:34px 18px;}
    .cc-pae .cc-empty-txt{color:var(--muted,#6b7280);margin-bottom:12px;}
    .cc-pae .pae-rows{display:flex;flex-direction:column;}
    .cc-pae .pae-row{display:flex;gap:14px;align-items:center;padding:11px 12px;border-radius:9px;text-decoration:none;color:inherit;}
    .cc-pae .pae-row:hover{background:var(--surface-2,rgba(0,0,0,.03));}
    .cc-pae .pae-row + .pae-row{border-top:1px solid var(--stroke,rgba(0,0,0,.07));}
    .cc-pae .pae-folio{font-weight:800;font-family:var(--mono,monospace);flex:none;min-width:88px;}
    .cc-pae .pae-ver{flex:none;font-size:.72rem;font-weight:800;color:var(--brand,#ff9900);border:1px solid var(--stroke,rgba(0,0,0,.12));border-radius:20px;padding:2px 9px;}
    .cc-pae .pae-label{font-weight:600;flex:1;min-width:0;}
    .cc-pae .pae-when{font-size:.85rem;color:var(--muted,#6b7280);}
    .cc-pae .pae-by{font-size:.85rem;color:var(--muted,#6b7280);min-width:110px;text-align:right;}
    @media (max-width:620px){
        .cc-pae .pae-row{flex-wrap:wrap;}
        .cc-pae .pae-by{text-align:left;min-width:0;}
    }
</style>
@endpush
