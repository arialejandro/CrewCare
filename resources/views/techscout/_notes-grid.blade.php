{{-- ============================================================================================
     TECH SCOUT — REJILLA DE NOTAS. Vive aparte porque se pinta en DOS momentos: dentro de la
     pantalla de trabajo, y sola, por el refresco periódico (GET /tech-scout/{id}/notas).

     Que sea el MISMO archivo no es limpieza: es la única forma de que lo que aparece solo al
     refrescar sea exactamente lo mismo que aparece al cargar. Dos plantillas para la misma
     rejilla se desincronizan — ya nos pasó con los campos de la nota.

     `data-ts-sig` es la FIRMA del contenido: cuántas notas hay y cuál fue el último cambio. El
     refresco compara firmas y sólo reemplaza la rejilla si cambió algo; así, quien está leyendo
     una nota no ve la pantalla parpadear cada veinte segundos sin motivo.
============================================================================================ --}}
@php
    $sig = $notas->count() . ':' . ($notas->max('id') ?? 0) . ':' . ($notas->max('updated_at')?->timestamp ?? 0);
@endphp
<div id="ts-notes" data-ts-sig="{{ $sig }}" data-ts-count="{{ $notas->count() }}">
    @if (! $notas->count())
        <div class="card ts-empty">Todavía no hay notas. Agrega la primera arriba.</div>
    @else
        <div class="ts-grid">
            @foreach ($notas as $n)
                <div class="ts-cell">
                    @if ($n->photo_path)<img src="{{ $n->photo_path }}" alt="" loading="lazy">@endif
                    <div class="ts-cell__bd">
                        @if ($n->story_label)<span class="ts-cell__tag">{{ $n->story_label }}</span>@endif
                        @if ($n->note)<p class="ts-cell__tx">{{ $n->note }}</p>@endif
                        <div class="ts-cell__meta">
                            <span>{{ $n->created_at->format('d/m H:i') }}</span>
                            <span>{{ $n->authorName() }}</span>
                            @if ($n->wasEdited())<span class="ed">editada</span>@endif
                        </div>

                        {{-- EDITAR EN LÍNEA. El owner lo pidió para no tener que volver a fotografiar
                             lo mismo y saturar el documento de información repetida. Se usa
                             <details>, que es HTML nativo: no necesita JavaScript, no rompe la CSP y
                             funciona sin red. Sólo el AUTOR: cada quien corrige lo suyo. Y editar
                             deja marca — es lo que sostiene el "si no está en las notas, no se pidió".

                             🪤 El refresco periódico NO pisa un editor abierto: si hay un <details>
                             abierto en la rejilla, salta el ciclo. Reemplazar el HTML mientras
                             alguien escribe le borraría lo escrito, que es justo el pecado que este
                             módulo lleva toda la semana evitando. --}}
                        @if ((int) $n->created_by_id === (int) auth()->id())
                            <details class="ts-edit">
                                <summary>Editar</summary>
                                <form action="{{ route('techscout.note.update', [$scout->id, $n->id]) }}" method="POST" class="mt-2">
                                    @csrf
                                    @method('PUT')
                                    <textarea name="note" class="form-control form-control-sm" rows="3"
                                              maxlength="2000">{{ $n->note }}</textarea>
                                    <input type="text" name="story_label" class="form-control form-control-sm mt-1"
                                           maxlength="120" value="{{ $n->story_label }}" placeholder="Nombre en la historia">
                                    <button type="submit" class="btn btn-crew-accent btn-sm mt-2">Guardar</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
