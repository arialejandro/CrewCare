{{-- Selector de idioma global (ES/EN). Enlaza la ruta con nombre locale.switch (valida contra
     la lista blanca config('app.locales')) y resalta el locale activo que fijó el middleware.
     Bootstrap 5.1.3: btn-group/btn (SIN utilidades -subtle/-emphasis). Se incluye en el header
     (usuarios autenticados) y en el login (visitante sin sesión). --}}
@php $active = app()->getLocale(); @endphp
<div class="btn-group btn-group-sm cc-lang" role="group" aria-label="Idioma / Language">
    @foreach(($locales ?? ['es', 'en']) as $loc)
        <a href="{{ route('locale.switch', $loc) }}"
           class="btn {{ $active === $loc ? 'btn-dark' : 'btn-outline-secondary' }}"
           @if($active === $loc) aria-current="true" @endif
           title="{{ $loc === 'es' ? 'Español' : 'English' }}">{{ strtoupper($loc) }}</a>
    @endforeach
</div>
