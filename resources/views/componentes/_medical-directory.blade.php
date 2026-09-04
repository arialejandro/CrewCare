{{-- _medical-directory.blade.php — CONTENIDO del home médico unificado (#usertable). (2026-07-31)

     FUENTE ÚNICA de la presentación: la usan el render inicial (admin/medicocrud) y el swap AJAX
     del buscador (componentes/search-results-medical). Muestra DOS secciones:
       · Integrantes del crew (cards con foto/iniciales) — parcial _crew-medical-cards.
       · Personas fuera del crew (sin cuenta) — cards de LitePatient.
     Si NINGUNA sección tiene resultados, un estado vacío ofrece registrar a la persona no-crew
     (abre el <details id="reg-nocrew"> que vive en la página, vía ccOpenRegister()).

     Recibe: $usuarios (paginador crew), $litePatients (colección), $liteCounts (array id=>conteo). --}}
@php
    $litePatients = $litePatients ?? collect();
    $liteCounts   = $liteCounts ?? [];
    $hasCrew = $usuarios->count() > 0;
    $hasLite = $litePatients->count() > 0;
@endphp

@if($hasCrew)
    <section class="cc-med-section">
        <h2 class="cc-med-section__title">
            @include('componentes._icon', ['name' => 'users', 'label' => null])
            {{ __('Integrantes del crew') }}
        </h2>
        @include('componentes._crew-medical-cards', ['usuarios' => $usuarios])
    </section>
@endif

@if($hasLite)
    <section class="cc-med-section">
        <h2 class="cc-med-section__title">
            @include('componentes._icon', ['name' => 'user', 'label' => null])
            {{ __('Personas fuera del crew') }}
            <span class="cc-med-section__hint">{{ __('(sin cuenta: extras, visitantes, proveedores)') }}</span>
        </h2>
        <div class="crew-cards">
            @foreach($litePatients as $lp)
                @php
                    $lpName  = $lp->displayName();
                    $lpInit  = strtoupper(mb_substr($lpName !== '' ? $lpName : '·', 0, 1));
                    $lpAge   = $lp->ageDisplay();
                    $lpCount = (int) ($liteCounts[$lp->id] ?? 0);
                    $lpSex   = $lp->sex ? (['F'=>'Femenino','M'=>'Masculino','O'=>'Otro'][$lp->sex] ?? $lp->sex) : null;
                @endphp
                <article class="crew-card crew-card--lite">
                    <div class="crew-card__hero is-empty">
                        <span class="crew-card__initials" aria-hidden="true">{{ $lpInit }}</span>
                        <span class="crew-card__tag">{{ __('Sin cuenta') }}</span>
                        <div class="crew-card__scrim">
                            <h2 class="crew-card__name">{{ $lpName }}</h2>
                            <p class="crew-card__role">{{ $lp->area ?: __('Sin área') }}</p>
                        </div>
                    </div>
                    <div class="crew-card__body">
                        <dl class="crew-card__meta">
                            <div><dt>{{ __('Edad') }}</dt><dd>{{ $lpAge !== null ? $lpAge : '—' }}</dd></div>
                            <div><dt>{{ __('Sexo') }}</dt><dd>{{ $lpSex ?: '—' }}</dd></div>
                            <div><dt>{{ __('Procedencia') }}</dt><dd>{{ $lp->origin ?: '—' }}</dd></div>
                        </dl>
                    </div>
                    <div class="crew-card__actions">
                        <a href="{{ route('lite.consulta.create', $lp->id) }}" class="crew-card__btn">
                            @include('componentes._icon', ['name' => 'heart-pulse', 'label' => null])
                            {{ __('Consulta') }}
                        </a>
                        <a href="{{ route('lite.historial', $lp->id) }}" class="crew-card__btn">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null])
                            {{ __('Historial') }}@if($lpCount) · {{ $lpCount }}@endif
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

@if(!$hasCrew && !$hasLite)
    <div class="crew-empty text-center py-5">
        <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
            @include('componentes._icon', ['name' => 'stethoscope', 'label' => null])
        </div>
        <h2 class="h6 mb-1">{{ __('No se encontró a nadie') }}</h2>
        <p class="text-muted mb-3">{{ __('Ni en el crew ni entre las personas sin cuenta.') }}</p>
        @if(auth()->check() && auth()->user()->isClinician())
            <button type="button" class="btn btn-primary cc-cta" data-cc-open-register>
                @include('componentes._icon', ['name' => 'user-plus', 'class' => 'cc-ico-18', 'label' => null])
                {{ __('Registrar persona fuera del crew') }}
            </button>
        @endif
    </div>
@endif
