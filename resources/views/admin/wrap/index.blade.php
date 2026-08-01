@extends('layouts.app')

@section('content')
@php
    $fmt = function ($f) { return $f ? \Carbon\Carbon::parse($f)->format('d M Y') : '—'; };
@endphp
<div class="container py-4">

  <div class="d-flex flex-wrap align-items-center justify-content-between mb-4" style="gap:12px">
    <div>
      <h1 class="h4 mb-1">Reporte final de wrap</h1>
      <p class="text-muted mb-0" style="font-size:.9rem">
        El documento de cierre de la producción: contrasta lo que el scouting predijo contra lo que realmente pasó.
      </p>
    </div>
    {{-- "Generar borrador" sólo para quien puede emitir: el safety manager o el safety asignado a
         la producción. El resto (incluido line-producer) lee los documentos ya emitidos, no genera. --}}
    @if($produccion && \App\Models\WrapReport::issuableBy(auth()->user(), $produccion))
      <a href="{{ route('wrap.preview') }}" class="btn btn-primary">
        @include('componentes._icon', ['name' => 'file-plus', 'class' => 'w-4 h-4']) Generar borrador
      </a>
    @endif
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-warning">{{ session('error') }}</div>
  @endif

  {{-- Aviso de ventana: mientras no pase la fecha de finalización, el wrap no se puede emitir.
       Se muestra a quien puede emitir, para que sepa por qué aún no ve la opción de sellar. --}}
  @if($produccion && \App\Models\WrapReport::issuableBy(auth()->user(), $produccion))
    @php $ventana = \App\Models\WrapReport::windowBlockedReason($produccion->end_date); @endphp
    @if($ventana)
      <div class="alert alert-info d-flex align-items-start" style="gap:8px">
        @include('componentes._icon', ['name' => 'lock', 'class' => 'w-4 h-4'])
        <span style="font-size:.88rem">{{ $ventana }}</span>
      </div>
    @endif
  @endif

  @unless($produccion)
    <div class="alert alert-warning">
      No hay una producción vigente. El reporte de wrap se genera sobre la producción activa.
    </div>
  @endunless

  @if($produccion)
  <div class="card mb-4">
    <div class="card-body">
      <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">Producción</div>
      <div class="h5 mb-1">{{ $produccion->name }}</div>
      <div class="text-muted" style="font-size:.85rem">
        {{ $produccion->client_name ?: 'Casa productora no registrada' }}
        · {{ $fmt($produccion->start_date) }} — {{ $fmt($produccion->end_date) }}
      </div>
    </div>
  </div>
  @endif

  <h2 class="h6 text-muted mb-3">Documentos emitidos</h2>

  @forelse($reportes as $r)
    <div class="card mb-2">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between" style="gap:10px">
        <div>
          <a href="{{ route('wrap.show', $r->id) }}" class="fw-bold text-decoration-none">{{ $r->folio() }}</a>
          <span class="text-muted" style="font-size:.85rem"> · {{ $fmt($r->period_start) }} a {{ $fmt($r->period_end) }}</span>
          @if($r->issued_at)
            <div class="text-muted" style="font-size:.78rem">Emitido {{ \Carbon\Carbon::parse($r->issued_at)->format('d M Y H:i') }}</div>
          @endif
          @if($r->addendums && count($r->addendums))
            <div class="mt-1" style="font-size:.78rem">
              {{ count($r->addendums) }} anexo(s):
              @foreach($r->addendums as $ax)
                <a href="{{ route('wrap.show', $ax->id) }}" class="badge bg-light text-dark border text-decoration-none">{{ $ax->folio() }}</a>
              @endforeach
            </div>
          @endif
        </div>
        <a href="{{ route('wrap.show', $r->id) }}" class="btn btn-sm btn-outline-secondary">
          @include('componentes._icon', ['name' => 'eye', 'class' => 'w-4 h-4']) Ver
        </a>
      </div>
    </div>
  @empty
    <div class="text-muted" style="font-size:.9rem">Aún no se ha emitido ningún reporte de wrap para esta producción.</div>
  @endforelse

</div>
@endsection
