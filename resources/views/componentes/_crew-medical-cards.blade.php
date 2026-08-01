{{-- CARDS de la lista de Consulta Médica (2026-07-24 · PASO 3/3, item 5).

     FUENTE ÚNICA de la presentación: la usan el render inicial (admin/medicocrud) y el swap AJAX
     del buscador (componentes/search-results-medical). Si sólo una de las dos la usara, escribir
     en el buscador cambiaría el diseño a media pantalla.

     ANATOMÍA (rediseño 2026-07-24 tras feedback del owner: la v1 salía confusa y saturada):
     la FOTO es el fondo de la tarjeta, con un velo oscuro de abajo hacia arriba sobre el que
     descansa la identidad. Debajo, los datos; al pie, las dos acciones en tono discreto. El ojo
     recorre cara → nombre → datos → acción, que es el orden en que el médico decide a quién
     atender. La mayoría del crew NO tiene foto (84 de 92 al 2026-07-24): sin ella la tarjeta cae
     a un panel con las iniciales en grande, que es el estado NORMAL, no un avatar roto.

     PRIVACIDAD: se muestra EDAD, nunca la fecha de nacimiento completa, y NUNCA teléfono ni email
     (la búsqueda usa un preset propio que ni siquiera los selecciona — ver SearchController).

     ICONOS: `_icon` emite un <svg> SIN atributos width/height — depende por completo del CSS. Las
     clases cc-ico-14/16 viven en `_form-kit`, que esta página NO incluye, así que aquí salían a
     tamaño natural (gigantes). Se usa `.cc-ico` a secas y lo dimensiona el bloque de estilos de
     medicocrud. No añadas cc-ico-NN aquí sin traerte también su definición.

     "Expediente pendiente": una sola consulta a `formularios` por página — no N+1. Distingue SIN
     expediente de expediente CON alergias vacías, que es la sutileza real: "existe fila" no
     garantiza que esté completo.

     Recibe: $usuarios (paginado). --}}
@php
    // (2026-07-24 · PIEZA 3) Fuente única de "el expediente de X": formulario::vigentesDe().
    // Antes esto era un pluck('alergy','id_user'), que ante dos filas se quedaba con la ÚLTIMA
    // mientras el médico leía la PRIMERA (cmedicController hacía ->first() sin ORDER BY). El
    // badge de esta tarjeta y la consulta podían contradecirse en las alergias — el dato que
    // contraindica. Sigue siendo UNA consulta por página, no N+1.
    $ids = collect($usuarios->items())->pluck('id')->all();
    $intake = \App\Models\formulario::vigentesDe($ids);
@endphp

<div class="crew-cards">
    @forelse ($usuarios as $user)
        @php
            // estadoDe() aplica el MISMO testigo (`alergy`) que ve el médico en su aviso.
            $expediente = isset($intake[$user->id]) ? $intake[$user->id] : null;
            $estado     = \App\Models\formulario::estadoDe($expediente);
            $pendiente  = $estado === 'ok' ? null : $estado;
            $iniciales       = strtoupper(mb_substr($user->name ?? '', 0, 1)) . strtoupper(mb_substr($user->lname ?? '', 0, 1));
            $nombre          = trim($user->name . ' ' . $user->lname);
        @endphp
        <article class="crew-card">

            {{-- Retrato: la foto ES el fondo; el velo garantiza contraste del nombre encima. --}}
            @php $tieneFoto = \App\Support\Avatar::has($user); @endphp
            <div class="crew-card__hero{{ $tieneFoto ? '' : ' is-empty' }}">
                @if($tieneFoto)
                    <img src="{{ \App\Support\Avatar::url($user) }}" alt="" loading="lazy">
                @else
                    <span class="crew-card__initials" aria-hidden="true">{{ $iniciales }}</span>
                @endif
                <div class="crew-card__scrim">
                    <h2 class="crew-card__name">{{ $nombre }}</h2>
                    <p class="crew-card__role">{{ \App\Models\User::positionNameFor($user->id ?? null, $user->puestodepartamento ?? null) ?: '—' }}</p>
                </div>
            </div>

            <div class="crew-card__body">
                <dl class="crew-card__meta">
                    <div><dt>{{ __('Departamento') }}</dt><dd>{{ \App\Models\User::departmentNameFor($user->id ?? null, $user->zone ?? null) ?: '—' }}</dd></div>
                    <div><dt>{{ __('Edad') }}</dt><dd>{{ $user->borndate ? \Carbon\Carbon::parse($user->borndate)->age : '—' }}</dd></div>
                    <div><dt>{{ __('Sexo') }}</dt><dd>{{ $user->sex ?: '—' }}</dd></div>
                </dl>

                @if($pendiente === 'missing')
                    <p class="crew-card__flag" title="{{ __('No ha llenado el cuestionario médico. Se puede atender igual; quedará marcado.') }}">
                        @include('componentes._icon', ['name' => 'alert-triangle', 'label' => null])
                        {{ __('Expediente pendiente') }}
                    </p>
                @elseif($pendiente === 'incomplete')
                    <p class="crew-card__flag crew-card__flag--soft" title="{{ __('Tiene expediente pero el campo de alergias está vacío.') }}">
                        @include('componentes._icon', ['name' => 'alert-triangle', 'label' => null])
                        {{ __('Sin alergias registradas') }}
                    </p>
                @endif
            </div>

            {{-- Acciones al pie, en tono discreto: la tarjeta es para ELEGIR persona; el peso
                 visual debe estar en la cara y el nombre, no en dos botones de color. --}}
            <div class="crew-card__actions">
                <a href="{{ url('/consulta/'.$user->id) }}" class="crew-card__btn">
                    @include('componentes._icon', ['name' => 'heart-pulse', 'label' => null])
                    {{ __('Consulta') }}
                </a>
                <a href="{{ url('/historialWR/'.$user->id) }}" class="crew-card__btn">
                    @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null])
                    {{ __('Historial') }}
                </a>
            </div>
        </article>
    @empty
        <div class="crew-empty text-center py-5">
            <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                @include('componentes._icon', ['name' => 'stethoscope', 'label' => null])
            </div>
            <h2 class="h6 mb-1">{{ __('Sin resultados') }}</h2>
            <p class="text-muted mb-0">{{ __('No hay integrantes del crew que mostrar.') }}</p>
        </div>
    @endforelse
</div>

@if($usuarios->hasPages())
    <div class="pt-3">{!! $usuarios->links() !!}</div>
@endif
