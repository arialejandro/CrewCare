@extends('layouts.app')
@section('title', $scout->location_name . ' · Tech Scout')

@push('styles')
{{-- Sin esto, las clases btn-crew-* no existen y los botones se vuelven invisibles sobre el tema
     oscuro (texto oscuro sin fondo). Ver la nota en create.blade.php. --}}
@include('componentes._crew-list-styles')
<style>
    .ts-doc-head{margin-bottom:1.5rem}
    .ts-doc-head h1{font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(1.3rem,2.4vw,1.8rem);margin:.3rem 0 .2rem;color:var(--text)}
    .ts-doc-head p{color:var(--text-muted);font-size:.88rem;margin:0}

    /* Una nota = una tarjeta. La FOTO manda; todo lo demás es discreto y va DEBAJO, nunca encima:
       superponer la hora sobre la imagen estropearía justo lo que el documento existe para mostrar. */
    .ts-note{display:flex;gap:1rem;padding:1rem 0;border-bottom:1px solid var(--stroke)}
    .ts-note:last-child{border-bottom:0}
    .ts-note-photo{flex:0 0 200px;max-width:200px}
    .ts-note-photo img{width:100%;height:auto;display:block;border-radius:10px;border:1px solid var(--stroke)}
    .ts-note-body{flex:1 1 auto;min-width:0}
    .ts-note-text{color:var(--text);font-size:.95rem;line-height:1.55;white-space:pre-wrap;margin:0 0 .45rem}
    .ts-note-label{display:inline-block;font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
        color:var(--brand-primary);background:color-mix(in srgb,var(--brand-primary) 12%,transparent);
        border:1px solid color-mix(in srgb,var(--brand-primary) 30%,transparent);border-radius:999px;padding:.2rem .55rem;margin-bottom:.4rem}
    /* Pie de la nota: hora, autor y marca de edición. Discreto a propósito. */
    .ts-note-meta{font-size:.72rem;color:var(--text-muted);display:flex;gap:.8rem;flex-wrap:wrap;align-items:center}
    .ts-note-meta .ts-edited{font-style:italic}
    @media (max-width:640px){ .ts-note{flex-direction:column} .ts-note-photo{flex:1 1 auto;max-width:100%} }

    .ts-empty{text-align:center;padding:2.5rem 1rem;color:var(--text-muted)}
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-4" style="max-width:860px">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap ts-doc-head">
        <div>
            <span style="font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700">Tech Scout</span>
            <h1>{{ $scout->location_name }}</h1>
            @if ($scout->location_address)<p>{{ $scout->location_address }}</p>@endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('techscout.document', $scout->id) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'file-text', 'label' => null])
                <span>Ver documento</span>
            </a>
            <a href="{{ route('techscout.index') }}" class="btn btn-crew-soft">Volver</a>
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    {{-- ── CAPTURA ──────────────────────────────────────────────────────────────────────
         Arriba del todo y siempre visible: es lo que se hace noventa veces en un recorrido.
         Enterrarla bajo la lista obligaría a bajar cada vez. --}}
    <form action="{{ route('techscout.note.store', $scout->id) }}" method="POST"
          enctype="multipart/form-data" class="card p-3 p-md-4 mb-4">
        @csrf

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold" for="photo">Foto</label>
                {{-- SIN `capture`: en set hacen falta las dos opciones, cámara y galería.
                     A veces la foto ya se tomó antes, o la tomó otra persona. --}}
                <input type="file" name="photo" id="photo" class="form-control"
                       accept="image/*,.heic,.heif" data-cc-photo>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold" for="note">Qué hay que resolver</label>
                <textarea name="note" id="note" class="form-control" rows="3" maxlength="2000"
                          placeholder="Ej. «Quitar las cortinas de esta ventana»">{{ old('note') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold" for="story_label">Nombre en la historia <span class="cc-muted fw-normal">(opcional)</span></label>
                {{-- Se arrastra de la nota anterior: si están una mañana entera en "Depa Pablo" se
                     escribe UNA vez. Sólo hace falta en multilocación; en una locación única, vacío.
                     Se descartó etiquetar por área física (cocina, fachada…) porque no generaliza:
                     una bodega no tiene los cuartos de una casa. --}}
                <input type="text" name="story_label" id="story_label" class="form-control"
                       value="{{ old('story_label', $lastLabel) }}" maxlength="120"
                       placeholder="Ej. «Depa Pablo»">
                <small class="cc-muted d-block mt-1">Se mantiene para las siguientes notas. Bórralo al cambiar de espacio.</small>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-crew-accent">Agregar nota</button>
            </div>
        </div>
    </form>

    {{-- ── LAS NOTAS, EN ORDEN DE RECORRIDO ─────────────────────────────────────────────
         Cronológico: es el orden en que se caminó, y el único que vale igual en una casa que
         en una bodega. --}}
    <div class="card p-3 p-md-4">
        <h2 class="h6 mb-3" style="font-family:'Poppins',sans-serif;font-weight:700">
            {{ $scout->notes->count() }} {{ $scout->notes->count() === 1 ? 'nota' : 'notas' }}
        </h2>

        @if (! $scout->notes->count())
            <div class="ts-empty">Todavía no hay notas. Agrega la primera arriba.</div>
        @else
            @foreach ($scout->notes as $n)
                <div class="ts-note">
                    @if ($n->photo_path)
                        <div class="ts-note-photo"><img src="{{ $n->photo_path }}" alt="" loading="lazy"></div>
                    @endif
                    <div class="ts-note-body">
                        @if ($n->story_label)<span class="ts-note-label">{{ $n->story_label }}</span>@endif
                        @if ($n->note)<p class="ts-note-text">{{ $n->note }}</p>@endif
                        <div class="ts-note-meta">
                            <span>{{ $n->created_at->format('d/m/Y H:i') }}</span>
                            {{-- El autor es dato INTERNO: aquí se ve para que entre ellos sepan quién
                                 reportó qué; en el PDF que va a arte NO se imprime. --}}
                            <span>{{ $n->authorName() }}</span>
                            @if ($n->wasEdited())
                                <span class="ts-edited">editada {{ $n->edited_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

</div>
@endsection
