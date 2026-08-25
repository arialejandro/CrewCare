@extends('layouts.app')
@section('content')
@push('styles')
    @include('componentes._crew-list-styles')
@endpush
{{-- BANDEJA "POR AUTORIZAR" — el DISPARADOR de descubrimiento del autorizador: los tratos crew_work
     capturados, sin sobre aún, que este usuario puede autorizar. Espejo de "Contratos por firmar".
     Cada fila lleva a la ficha del payee, donde firma la autorización (que dispara el contrato). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Infosheets por autorizar') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Tratos capturados que esperan tu autorización para generar el contrato.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif

        @if($contracts->isEmpty())
            <div class="text-center text-muted py-5">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null])
                <p class="mb-0 mt-2">{{ __('No tienes tratos pendientes por autorizar.') }}</p>
            </div>
        @else
            {{-- AUTORIZAR EN LOTE: una producción son cientos de tratos y el autorizador es UNA
                 persona. Con la firma ya adoptada se marcan los que van y se autorizan de una.
                 Sin firma adoptada no hay lote: primero se autoriza una a mano guardándola. --}}
            <form method="POST" action="{{ route('infosheet.batch') }}" id="isBatch">
                @csrf
                @if($adopted)
                    <div class="card mb-3">
                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                            <label class="d-inline-flex align-items-center gap-2 mb-0 fw-semibold">
                                <input type="checkbox" id="isAll"> {{ __('Seleccionar todo') }}
                            </label>
                            <span class="small text-muted">
                                {{ __('Tu firma guardada:') }}
                                <img src="{{ $adopted }}" alt="" style="height:28px;background:#fff;border-radius:4px;padding:2px;vertical-align:middle">
                            </span>
                            <button type="submit" class="btn btn-sm btn-crew d-inline-flex align-items-center gap-1 ms-auto" id="isBatchBtn" disabled>
                                @include('componentes._icon', ['name' => 'check-circle', 'label' => null])
                                {{ __('Autorizar seleccionadas con mi firma') }} (<span id="isCount">0</span>)
                            </button>
                        </div>
                        <div class="card-footer small text-muted">
                            {{ __('Autorizar emite el contrato y lo manda a firma; ya no se puede editar.') }}
                            {{ __('Se procesan hasta :n por tanda.', ['n' => $batchMax]) }}
                        </div>
                    </div>
                @else
                    <div class="alert alert-info small">
                        {{ __('¿Vas a autorizar muchas? Autoriza la primera desde su ficha marcando “Guardar para reúso”: a partir de ahí tu firma se aplica sola y podrás autorizarlas en lote desde aquí.') }}
                    </div>
                @endif

                <div class="d-flex flex-column gap-2">
                    @foreach($contracts as $c)
                        @php $p = $c->payee; @endphp
                        <div class="card">
                            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    @if($adopted)
                                        <input type="checkbox" class="is-pick" name="contract_ids[]" value="{{ $c->id }}" aria-label="{{ optional($p)->name }}">
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ optional($p)->name ?: __('(sin nombre)') }}</div>
                                        <div class="small text-muted">
                                            {{ $c->title ?: ($c->crew_activity ?: __('Trato de crew')) }}
                                            @if(optional($c->department)->name) · {{ $c->department->name }}@endif
                                            @if($c->fee_amount > 0) · {{ number_format((float) $c->fee_amount, 2) }} {{ $c->fee_currency ?: 'MXN' }}@endif
                                        </div>
                                    </div>
                                </div>
                                @if($p)
                                    <a href="{{ route('payees.show', $p) }}#autorizar-infosheet"
                                       class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                                        @include('componentes._icon', ['name' => 'file-text', 'label' => null]) {{ __('Revisar') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </form>
        @endif

    </div>
</div>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('isBatch');
    if (!form) { return; }
    var all   = document.getElementById('isAll');
    var btn   = document.getElementById('isBatchBtn');
    var count = document.getElementById('isCount');
    var MAX   = {{ (int) ($batchMax ?? 25) }};
    function picks() { return Array.prototype.slice.call(form.querySelectorAll('.is-pick')); }
    function sync() {
        var n = picks().filter(function (c) { return c.checked; }).length;
        if (count) { count.textContent = n; }
        if (btn) { btn.disabled = n === 0; }
    }
    picks().forEach(function (c) { c.addEventListener('change', sync); });
    if (all) {
        all.addEventListener('change', function () {
            // El tope por tanda se respeta también al "seleccionar todo".
            var n = 0;
            picks().forEach(function (c) { c.checked = all.checked && n++ < MAX; });
            sync();
        });
    }
    // Autorizar emite contratos: se confirma cuántos, con su número a la vista.
    form.addEventListener('submit', function (e) {
        var n = picks().filter(function (c) { return c.checked; }).length;
        if (!n) { e.preventDefault(); return; }
        if (!window.confirm('{{ __('Se autorizarán') }} ' + n + ' {{ __('hoja(s) y se emitirán sus contratos. ¿Continuar?') }}')) {
            e.preventDefault(); return;
        }
        if (btn) { btn.disabled = true; btn.textContent = '{{ __('Autorizando…') }}'; }
    });
    sync();
})();
</script>
@endpush
@endsection
