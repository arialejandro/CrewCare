{{--
    _consulta-campos.blade.php — TARJETA "Datos de la consulta", FUENTE ÚNICA (2026-07-31).

    El MISMO formulario rico para el crew (admin/cmedica) y para el paciente sin cuenta
    (admin/lite/consulta). Antes eran dos vistas que divergían solas: la de crew tenía la tabla
    de medicamentos con autocompletado, píldoras de manejo y campo de información adicional; la
    lite salía pobre (inputs planos, sin datalist, sin info adicional). Se extrajo aquí para que
    NUNCA vuelvan a divergir — misma doctrina que _cintillo-previo y construirMedicamentos().

    El <form> y @csrf viven en la vista padre (la acción difiere: cmedica.store vs lite.consulta.store).
    Este parcial renderiza la tarjeta de captura + la barra de acción.

    Recibe:
      $catalog            colección de Medication (para los datalist de nombre/dosis)
      $presentations      Medication::PRESENTATIONS (opciones del select de presentación)
      $managementOptions  cmedic::MANAGEMENT_OPTIONS (opcional; si no viene, no se pinta el bloque)
      $recentInjuries     accidentes recientes (SOLO crew; lite no lo pasa → no se pinta la liga)
      $cancelUrl          destino del botón Cancelar (default /medicocrud)
      $submitLabel        texto del botón de guardar (default "Guardar consulta")
--}}
@php
    $cancelUrl   = $cancelUrl   ?? url('/medicocrud');
    $submitLabel = $submitLabel ?? __('Guardar consulta');

    $oldNames   = old('med_name', []);
    $oldQtys    = old('med_qty', []);
    $oldDosages = old('med_dosage', []);
    $oldPres    = old('med_presentation', []);
    $rows       = max(count($oldNames), 1);
    $catNames   = isset($catalog) ? $catalog->pluck('name')->unique()->values() : collect();
    $catDosages = isset($catalog) ? $catalog->pluck('dosage')->filter()->unique()->values() : collect();
    $presentations = $presentations ?? [];
@endphp

@once
@push('styles')
<style>
    /* Estilos propios de la tarjeta de consulta (lo que el kit no cubre): tabla densa de
       medicamentos, píldoras de manejo y barra de acción pegajosa. Se emiten UNA vez. */
    .cc-consulta { color: var(--text); }

    .cc-consulta .cc-med-table { margin-bottom: 0; }
    .cc-consulta .cc-med-table th {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
        color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border);
        padding: .5rem .5rem; white-space: nowrap;
    }
    .cc-consulta .cc-med-table td {
        vertical-align: middle; padding: .45rem .5rem; border-bottom: 1px solid var(--border);
    }
    .cc-consulta .cc-med-table tbody tr:last-child td { border-bottom: 0; }
    .cc-consulta .cc-med-num { color: var(--text-muted); font-variant-numeric: tabular-nums; font-weight: 600; }
    .cc-consulta .cc-med-del {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 9px;
        border: 1px solid var(--stroke-2, var(--border)); background: transparent;
        color: var(--text-muted); transition: color .15s ease, border-color .15s ease, background-color .15s ease;
    }
    .cc-consulta .cc-med-del:hover { color: var(--danger); border-color: var(--danger); background: color-mix(in srgb, var(--danger) 10%, transparent); }

    .cc-consulta .cc-mgmt { display: flex; flex-wrap: wrap; gap: .4rem .5rem; }
    .cc-consulta .cc-mgmt__opt {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .45rem .8rem; margin: 0; min-height: 44px;
        border: 1px solid var(--stroke-2, var(--border)); border-radius: 999px;
        background: var(--surface-2); color: var(--text);
        font-size: .9rem; cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .cc-consulta .cc-mgmt__opt:hover { border-color: var(--brand-primary, var(--border)); }
    .cc-consulta .cc-mgmt__opt:focus-within { outline: 2px solid var(--brand-primary, currentColor); outline-offset: 2px; }
    .cc-consulta .cc-mgmt__opt input { margin: 0; cursor: pointer; }

    .cc-consulta .cc-actionbar {
        position: sticky; bottom: 0; z-index: 5;
        display: flex; justify-content: flex-end; gap: .6rem; flex-wrap: wrap;
        padding: .85rem 0 .25rem;
        margin-top: .25rem;
    }
    @media (max-width: 575.98px) {
        .cc-consulta .cc-actionbar .cc-cta,
        .cc-consulta .cc-actionbar .cc-btn-ghost { flex: 1 1 auto; }
    }

    /* ── Móvil: la tabla de medicamentos se apila en tarjetas (mata el scroll
       horizontal, antes se recortaba la columna Dosis/Presentación). Mismo enfoque
       que .cc-stack del scouting: <thead> oculto, cada <tr> = tarjeta, cada <td> a
       lo ancho con su etiqueta (data-label vía ::before) y el input/select al 100%.
       En ≥768px la tabla conserva su min-width y se ve como tabla normal. ── */
    @media (min-width: 768px) {
        .cc-consulta .cc-med-table { min-width: 620px; }
    }
    @media (max-width: 767px) {
        .cc-consulta .cc-med-table,
        .cc-consulta .cc-med-table tbody,
        .cc-consulta .cc-med-table tr,
        .cc-consulta .cc-med-table td { display: block; width: 100%; }
        .cc-consulta .cc-med-table thead {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
        }
        .cc-consulta .cc-med-table tbody tr {
            margin: 0 0 .75rem; padding: .35rem .7rem .55rem;
            border: 1px solid var(--border); border-radius: 12px; background: var(--surface-2);
        }
        .cc-consulta .cc-med-table td {
            padding: .4rem 0; border: 0; border-bottom: 1px solid var(--border);
        }
        .cc-consulta .cc-med-table td:last-child { border-bottom: 0; text-align: right; }
        .cc-consulta .cc-med-table td[data-label]::before {
            content: attr(data-label);
            display: block; margin-bottom: .25rem;
            font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
            font-weight: 600; color: var(--text-muted);
        }
        .cc-consulta .cc-med-table td .form-control,
        .cc-consulta .cc-med-table td .form-select { width: 100%; }
        /* El # actúa como título de la tarjeta; el botón de borrar queda a la derecha. */
        .cc-consulta .cc-med-table td.cc-med-num {
            padding: .1rem 0 .35rem; color: var(--text); font-weight: 700; font-size: .95rem;
        }
        .cc-consulta .cc-med-table td.cc-med-num::before { content: "#"; color: var(--text-muted); }
    }
</style>
@endpush
@endonce

<div class="cc-form-card">
    <div class="cc-form-card__head">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div class="cc-form-card__titles">
            <h2 class="cc-form-card__title">{{ __('Datos de la consulta') }}</h2>
            <p class="cc-form-card__sub">{{ __('Diagnóstico, medicamentos y observaciones de la atención') }}</p>
        </div>
    </div>
    <div class="cc-form-card__body">

        <div class="row g-3 mb-2">
            <div class="col-md-3">
                <div class="cc-field">
                    <label for="consultation_date" class="cc-label">{{ __('Fecha de atención') }}</label>
                    <input id="consultation_date" type="date" name="consultation_date" class="form-control cc-control" max="{{ date('Y-m-d') }}" value="{{ old('consultation_date', date('Y-m-d')) }}">
                </div>
            </div>
            <div class="col-md-9">
                <div class="cc-field">
                    <label for="diagnosis" class="cc-label">{{ __('Diagnóstico') }} <span class="cc-req" aria-hidden="true">*</span></label>
                    <input id="diagnosis" type="text" name="diagnosis" value="{{ old('diagnosis') }}" class="form-control cc-control @error('diagnosis') is-invalid @enderror" placeholder="{{ __('Ej. Gastroenteritis infecciosa + Gastritis') }}" required maxlength="500">
                    @error('diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ===== Manejo / conducta (2026-07-24 · PASO 3/3, item 2) =====
             No toda consulta lleva medicamento: una valoración, un reposo o una referencia también
             son historia clínica. Sin este campo esas consultas salen mudas y, para la vigilancia
             epidemiológica, INVISIBLES. Múltiple, y puede convivir con medicamentos o ir solo. --}}
        @isset($managementOptions)
        <div class="cc-field">
            <span class="cc-label d-block">{{ __('Manejo / conducta') }} <span class="cc-optional">({{ __('opcional, puedes elegir varias') }})</span></span>
            @php $oldMgmt = (array) old('management', []); @endphp
            <div class="cc-mgmt" role="group" aria-label="{{ __('Manejo o conducta clínica') }}">
                @foreach($managementOptions as $key => $label)
                    <label class="cc-mgmt__opt" for="mgmt-{{ $key }}">
                        <input id="mgmt-{{ $key }}" type="checkbox" name="management[]" value="{{ $key }}"
                               {{ in_array($key, $oldMgmt, true) ? 'checked' : '' }}>
                        <span>{{ __($label) }}</span>
                    </label>
                @endforeach
            </div>
            <span class="cc-help">{{ __('Qué se hizo en la consulta, aunque no se haya recetado nada.') }}</span>
        </div>
        @endisset

        {{-- (Ola A) Liga OPCIONAL a un accidente laboral: al ligarla, la consulta se inyecta al DSR
             del día. Sólo se pinta si la vista padre cargó accidentes (crew); lite no lo pasa. --}}
        @isset($recentInjuries)
        @if($recentInjuries->count())
        <div class="cc-field">
            <label for="injury_report_id" class="cc-label">
                {{ __('Ligar a accidente laboral') }} <span class="cc-optional">({{ __('opcional') }})</span>
            </label>
            <select id="injury_report_id" name="injury_report_id" class="form-select cc-select @error('injury_report_id') is-invalid @enderror">
                <option value="">— {{ __('Sin ligar a accidente') }} —</option>
                @foreach($recentInjuries as $inj)
                    <option value="{{ $inj->id }}" {{ (string) old('injury_report_id') === (string) $inj->id ? 'selected' : '' }}>
                        #{{ $inj->id }} · {{ $inj->production_title ?: 'Sin producción' }}{{ $inj->incident_date ? ' · ' . \Carbon\Carbon::parse($inj->incident_date)->format('d/m/Y') : '' }}
                    </option>
                @endforeach
            </select>
            @error('injury_report_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">{{ __('Al ligarla, esta consulta se registra automáticamente en el DSR del día como evento crítico.') }}</span>
        </div>
        @endif
        @endisset

        {{-- Medicamentos estructurados --}}
        <div class="cc-field">
            <label class="cc-label d-block">
                @include('componentes._icon', ['name' => 'package', 'class' => 'cc-ico-16', 'label' => null])
                {{ __('Medicamentos administrados') }}
            </label>
            <span class="cc-help mb-2">{{ __('Agrega uno o más. Si escribes una variante nueva (nombre/dosis/presentación) se guarda en el catálogo para reutilizarla.') }}</span>

            <div class="table-responsive mt-2">
                <table class="table cc-med-table align-middle">
                    <thead>
                        <tr>
                            <th style="width:42px;">#</th>
                            <th style="width:90px;">{{ __('Cant.') }}</th>
                            <th>{{ __('Medicamento') }}</th>
                            <th style="width:120px;">{{ __('Dosis') }}</th>
                            <th style="width:170px;">{{ __('Presentación') }}</th>
                            <th style="width:44px;"></th>
                        </tr>
                    </thead>
                    <tbody id="medRows">
                        @for ($i = 0; $i < $rows; $i++)
                        <tr>
                            <td class="cc-med-num med-num">{{ $i + 1 }}</td>
                            <td data-label="{{ __('Cant.') }}"><input type="number" name="med_qty[]" class="form-control form-control-sm cc-control-sm med-qty" min="0" step="1" inputmode="numeric" value="{{ $oldQtys[$i] ?? 1 }}"></td>
                            <td data-label="{{ __('Medicamento') }}"><input type="text" name="med_name[]" list="med-names" class="form-control form-control-sm cc-control-sm" value="{{ $oldNames[$i] ?? '' }}" placeholder="Paracetamol" maxlength="150"></td>
                            <td data-label="{{ __('Dosis') }}"><input type="text" name="med_dosage[]" list="med-dosages" class="form-control form-control-sm cc-control-sm" value="{{ $oldDosages[$i] ?? '' }}" placeholder="500mg" maxlength="60"></td>
                            <td data-label="{{ __('Presentación') }}">
                                <select name="med_presentation[]" class="form-select form-select-sm cc-control-sm">
                                    <option value="">—</option>
                                    @foreach ($presentations as $p)
                                        <option value="{{ $p }}" {{ (($oldPres[$i] ?? '') === $p) ? 'selected' : '' }}>{{ $p }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-center"><button type="button" class="cc-med-del" data-med-del title="{{ __('Quitar medicamento') }}" aria-label="{{ __('Quitar medicamento') }}">@include('componentes._icon', ['name' => 'x', 'class' => 'cc-ico-16', 'label' => null])</button></td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <button type="button" class="cc-btn-ghost mt-2" data-med-add>
                @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-16', 'label' => null])
                {{ __('Agregar medicamento') }}
            </button>

            <datalist id="med-names">@foreach ($catNames as $n)<option value="{{ $n }}">@endforeach</datalist>
            <datalist id="med-dosages">@foreach ($catDosages as $d)<option value="{{ $d }}">@endforeach</datalist>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="cc-field">
                    <label for="observations" class="cc-label">{{ __('Observaciones') }}</label>
                    <textarea id="observations" name="observations" rows="3" class="form-control cc-control @error('observations') is-invalid @enderror" maxlength="2000" placeholder="{{ __('Evolución, indicaciones, seguimiento…') }}">{{ old('observations') }}</textarea>
                    @error('observations')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="cc-field">
                    <label for="aditional" class="cc-label">{{ __('Información adicional') }}</label>
                    <textarea id="aditional" name="aditional" rows="3" class="form-control cc-control" maxlength="2000" placeholder="{{ __('Referencia a hospital, incapacidad, etc.') }}">{{ old('aditional') }}</textarea>
                </div>
            </div>
        </div>

        <div class="cc-signnote mt-3">
            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-signnote__ico cc-ico-18', 'label' => null])
            <span>{{ __('Atendido por') }} <strong>{{ auth()->user()->name ?? '' }}</strong> · {{ __('se registra con tu usuario y la fecha (autofirma, no editable).') }}</span>
        </div>

    </div>
</div>

<div class="cc-actionbar">
    <a href="{{ $cancelUrl }}" class="cc-btn-ghost">{{ __('Cancelar') }}</a>
    <button type="submit" class="btn btn-primary cc-cta">
        @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
        {{ $submitLabel }}
    </button>
</div>

@once
@push('scripts')
<script>
    function renumberMedRows() {
        document.querySelectorAll('#medRows tr').forEach(function (tr, i) {
            var n = tr.querySelector('.med-num');
            if (n) n.textContent = i + 1;
        });
    }
    function addMedRow() {
        var tbody = document.getElementById('medRows');
        var first = tbody.querySelector('tr');
        var clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (inp) {
            inp.value = inp.classList.contains('med-qty') ? '1' : '';
        });
        var sel = clone.querySelector('select');
        if (sel) sel.selectedIndex = 0;
        tbody.appendChild(clone);
        renumberMedRows();
    }
    function removeMedRow(btn) {
        var tbody = document.getElementById('medRows');
        if (tbody.querySelectorAll('tr').length <= 1) {
            btn.closest('tr').querySelectorAll('input').forEach(function (inp) {
                inp.value = inp.classList.contains('med-qty') ? '1' : '';
            });
            var sel = btn.closest('tr').querySelector('select');
            if (sel) sel.selectedIndex = 0;
            return;
        }
        btn.closest('tr').remove();
        renumberMedRows();
    }

    // Delegación (CSP: sin on* en atributo). El botón "agregar" es único; los de borrar se clonan
    // con cada fila nueva, por eso ambos se escuchan por delegación en el documento.
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-med-add]')) { addMedRow(); return; }
        var del = e.target.closest('[data-med-del]');
        if (del) { removeMedRow(del); }
    });
</script>
@endpush
@endonce
