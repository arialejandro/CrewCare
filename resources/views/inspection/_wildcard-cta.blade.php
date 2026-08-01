{{-- Estado SIN RESULTADOS = estado diseñado, no error. Ofrece el comodín HER-026
     con selección de familia al vuelo. Espera $wildcard (puede ser null). --}}
<div class="insp-empty">
    @include('componentes._icon', ['name' => 'wrench', 'class' => 'cc-ico', 'label' => null])
    <h3>{{ __('¿No aparece la herramienta?') }}</h3>
    <p>{{ __('Ninguna herramienta del catálogo coincide. Inspecciónala como herramienta genérica y elige su familia al vuelo.') }}</p>
    @if ($wildcard)
        <a href="{{ route('tools.inspect.form', $wildcard->id) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
            @include('componentes._icon', ['name' => 'clipboard-check', 'label' => null])
            {{ __('Inspeccionar herramienta genérica') }}
        </a>
    @endif
</div>
