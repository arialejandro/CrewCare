@php
    // $event puede ser null (crear) o un modelo (editar). Todos los valores caen a old()
    // primero para conservar lo tecleado tras un error de validación.
    $e = $event ?? null;

    // Enum CERRADO del marco (los 7 colores de _badge-tokens). Se usa para VALIDAR el badge
    // de cada norma antes de pintarlo como clase CSS (y en JS antes de crear el chip).
    $badgeEnum = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL', 'AMAZON'];

    // IDs ya ligados (old() en fallo de validación, o normas actuales del evento). Se normalizan
    // a int para comparar sin sorpresas contra $s->id.
    $checkedIds = collect($linkedIds ?? [])->map(function ($v) { return (int) $v; })->all();

    // Selects A..E y 1..5 (mismos rangos que valida el Form Request).
    $likelihoods  = ['A', 'B', 'C', 'D', 'E'];
    $consequences = [1, 2, 3, 4, 5];
@endphp

{{-- _badge-tokens: los chips .badge-XXX del norm-picker necesitan su color. @once, echo in-place. --}}
@include('componentes._badge-tokens')

@once
@push('styles')
<style>
    /* Norm-picker (editor N:M evento↔norma). Vidrio + tokens de _brand-theme. */
    .he-picker{border:1px solid var(--stroke,var(--border));border-radius:var(--radius-sm,11px);background:var(--surface,transparent);overflow:hidden}
    .he-picker__bar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;padding:.75rem;border-bottom:1px solid var(--stroke,var(--border))}
    .he-picker__search{position:relative;flex:1 1 220px;min-width:0}
    .he-picker__search .form-control{padding-left:2.1rem}
    .he-picker__search .he-p-ico{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--text-muted);pointer-events:none}
    .he-picker__facets{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
    .he-picker .epf-chip{border:1px solid var(--stroke);background:var(--glass);color:var(--text-muted);border-radius:999px;padding:.3rem .65rem;font-size:.7rem;font-weight:700;letter-spacing:.03em;line-height:1;cursor:pointer;transition:background .12s,border-color .12s,color .12s}
    .he-picker .epf-chip:hover{border-color:var(--stroke-2);color:var(--text)}
    .he-picker .epf-chip[aria-pressed="true"]{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary)}

    /* Zona de seleccionadas (chips removibles renderizados POR JS con textContent). */
    .he-picker__selected{display:flex;flex-wrap:wrap;gap:.4rem;padding:.75rem;border-bottom:1px solid var(--stroke,var(--border));min-height:2.6rem}
    .he-picker__empty{color:var(--text-muted);font-size:.82rem}
    .he-selchip{display:inline-flex;align-items:center;gap:.4rem;padding:.28rem .3rem .28rem .5rem;border-radius:999px;border:1px solid var(--stroke);background:var(--glass-2);color:var(--text);font-size:.78rem;font-weight:600;line-height:1}
    .he-selchip .badge{font-weight:700;letter-spacing:.02em}
    .he-selchip__x{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border:0;border-radius:999px;background:color-mix(in srgb,var(--danger) 16%,transparent);color:var(--danger);font-size:.85rem;line-height:1;cursor:pointer;padding:0}
    .he-selchip__x:hover{background:color-mix(in srgb,var(--danger) 30%,transparent)}

    /* Lista de checkboxes (scrollable). */
    .he-picker__list{max-height:320px;overflow-y:auto;padding:.35rem;display:flex;flex-direction:column;gap:.15rem;scrollbar-width:thin;scrollbar-color:var(--stroke-2) transparent}
    .he-picker__list::-webkit-scrollbar{width:8px}
    .he-picker__list::-webkit-scrollbar-thumb{background:var(--stroke-2);border-radius:8px}
    .he-picker__item{display:flex;align-items:center;gap:.6rem;padding:.5rem .6rem;border-radius:var(--radius-sm,10px);cursor:pointer;margin:0}
    .he-picker__item:hover{background:var(--glass-2)}
    .he-picker__item input{width:18px;height:18px;flex:none;margin:0;accent-color:var(--brand-primary)}
    .he-picker__item.he-hidden{display:none}
    .he-picker__lbl{min-width:0;display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}
    .he-picker__lbl .badge{font-weight:700;letter-spacing:.02em}
    .he-picker__code{font-variant-numeric:tabular-nums;font-weight:600;color:var(--text)}
    .he-picker__cat{color:var(--text-muted);font-size:.84rem}
    .he-picker__none{padding:1.1rem;text-align:center;color:var(--text-muted);font-size:.85rem}
    .he-picker__nomatch{padding:1.1rem;text-align:center;color:var(--text-muted);font-size:.85rem}
</style>
@endpush
@endonce

{{-- ===== Identificación ===== --}}
<div class="cc-group-title">
    @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-14', 'label' => null])
    Identificación
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="cc-field">
            <label for="name_es" class="cc-label">Nombre del evento (ES) <span class="cc-req" aria-hidden="true">*</span></label>
            <input type="text" class="form-control cc-control @error('name_es') is-invalid @enderror"
                   id="name_es" name="name_es" value="{{ old('name_es', $e->name_es ?? '') }}"
                   maxlength="255" required>
            @error('name_es')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Un evento realista, p. ej. «Trabajo en altura montando parrilla de foro».</span>
        </div>
    </div>

    <div class="col-12">
        <div class="cc-field">
            <label for="name_en" class="cc-label">
                Nombre en inglés <span class="cc-optional">(opcional)</span>
            </label>
            <input type="text" class="form-control cc-control @error('name_en') is-invalid @enderror"
                   id="name_en" name="name_en" value="{{ old('name_en', $e->name_en ?? '') }}"
                   maxlength="255">
            @error('name_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Para consulta en inglés (se muestra cuando el idioma activo es EN).</span>
        </div>
    </div>
</div>

{{-- ===== Clasificación ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-14', 'label' => null])
    Clasificación
</div>
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="context" class="cc-label">Contexto <span class="cc-req" aria-hidden="true">*</span></label>
            @php $ctxOld = old('context', $e->context ?? ''); @endphp
            <select class="form-select cc-select @error('context') is-invalid @enderror"
                    id="context" name="context" required>
                <option value="" disabled {{ $ctxOld === '' ? 'selected' : '' }}>Selecciona el contexto…</option>
                @foreach($contexts as $ctxKey => $ctxLabel)
                    <option value="{{ $ctxKey }}" {{ $ctxOld === $ctxKey ? 'selected' : '' }}>{{ $ctxLabel }}</option>
                @endforeach
            </select>
            @error('context')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Dónde ocurre: locación, set, construcción, adaptación de foros o transversal.</span>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="category" class="cc-label">
                Categoría <span class="cc-optional">(opcional)</span>
            </label>
            @php $catOld = old('category', $e->category ?? ''); @endphp
            <select class="form-select cc-select @error('category') is-invalid @enderror"
                    id="category" name="category">
                <option value="" {{ $catOld === '' ? 'selected' : '' }}>— Sin categoría —</option>
                @foreach($categories as $catKey => $catLabel)
                    <option value="{{ $catKey }}" {{ $catOld === $catKey ? 'selected' : '' }}>{{ $catLabel }}</option>
                @endforeach
            </select>
            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Tipo de peligro (misma taxonomía que el Scouting).</span>
        </div>
    </div>
</div>

{{-- ===== Probabilidad y consecuencia por defecto ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-14', 'label' => null])
    Probabilidad y consecuencia por defecto
</div>
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="default_likelihood" class="cc-label">
                Probabilidad <span class="cc-optional">(opcional)</span>
            </label>
            @php $likeOld = old('default_likelihood', $e->default_likelihood ?? ''); @endphp
            <select class="form-select cc-select @error('default_likelihood') is-invalid @enderror"
                    id="default_likelihood" name="default_likelihood">
                <option value="" {{ $likeOld === '' ? 'selected' : '' }}>— Sin definir —</option>
                @foreach($likelihoods as $lk)
                    <option value="{{ $lk }}" {{ $likeOld === $lk ? 'selected' : '' }}>{{ $lk }}</option>
                @endforeach
            </select>
            @error('default_likelihood')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Nivel A–E de la matriz 5×5 (A = casi seguro … E = raro).</span>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="default_consequence" class="cc-label">
                Consecuencia <span class="cc-optional">(opcional)</span>
            </label>
            @php $consOld = (string) old('default_consequence', $e->default_consequence ?? ''); @endphp
            <select class="form-select cc-select @error('default_consequence') is-invalid @enderror"
                    id="default_consequence" name="default_consequence">
                <option value="" {{ $consOld === '' ? 'selected' : '' }}>— Sin definir —</option>
                @foreach($consequences as $cs)
                    <option value="{{ $cs }}" {{ $consOld === (string) $cs ? 'selected' : '' }}>{{ $cs }}</option>
                @endforeach
            </select>
            @error('default_consequence')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Severidad 1–5 de la matriz 5×5 (1 = insignificante … 5 = catastrófico).</span>
        </div>
    </div>
</div>

{{-- ===== Descripción ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-14', 'label' => null])
    Descripción
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="cc-field">
            <label for="description_es" class="cc-label">
                Descripción (ES) <span class="cc-optional">(opcional)</span>
            </label>
            <textarea class="form-control cc-control @error('description_es') is-invalid @enderror"
                      id="description_es" name="description_es" maxlength="600" rows="3">{{ old('description_es', $e->description_es ?? '') }}</textarea>
            @error('description_es')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-12">
        <div class="cc-field">
            <label for="description_en" class="cc-label">
                Descripción (EN) <span class="cc-optional">(opcional)</span>
            </label>
            <textarea class="form-control cc-control @error('description_en') is-invalid @enderror"
                      id="description_en" name="description_en" maxlength="600" rows="3">{{ old('description_en', $e->description_en ?? '') }}</textarea>
            @error('description_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ===== Normas ligadas (editor N:M) ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
    Normas ligadas
</div>
<div class="cc-field">
    <span class="cc-help mb-2 d-block">
        Liga las normas equivalentes (CSATF, OSHA, STPS…) que respaldan este evento. Se guardan al enviar el formulario.
    </span>

    <div class="he-picker" id="hePickerRoot">
        <div class="he-picker__bar">
            <div class="he-picker__search">
                <label for="hePickerSearch" class="visually-hidden">Buscar norma por código o categoría</label>
                <span class="he-p-ico">@include('componentes._icon', ['name' => 'search', 'label' => null])</span>
                <input type="search" id="hePickerSearch" class="form-control cc-control" autocomplete="off"
                       placeholder="Buscar norma por código o categoría…">
            </div>
            @php
                // Marcos presentes entre las normas ofrecidas (para las facetas del picker).
                $pickerMarcos = [];
                foreach ($allStandards as $__s) {
                    $__b = in_array($__s->regulation_badge, $badgeEnum, true) ? $__s->regulation_badge : 'GENERAL';
                    $pickerMarcos[$__b] = true;
                }
                $pickerMarcoChips = array_values(array_filter($badgeEnum, function ($m) use ($pickerMarcos) {
                    return isset($pickerMarcos[$m]);
                }));
            @endphp
            @if(count($pickerMarcoChips))
                <div class="he-picker__facets" role="group" aria-label="Filtrar normas por marco">
                    @foreach($pickerMarcoChips as $mk)
                        <button type="button" class="epf-chip" data-marco="{{ $mk }}" aria-pressed="false">{{ $mk }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Zona de seleccionadas: la rellena el JS (createElement + textContent). --}}
        <div class="he-picker__selected" id="hePickerSelected" aria-live="polite">
            <span class="he-picker__empty">Ninguna norma ligada todavía.</span>
        </div>

        <div class="he-picker__list" id="hePickerList">
            @forelse($allStandards as $s)
                @php
                    $badgeClass = in_array($s->regulation_badge, $badgeEnum, true) ? $s->regulation_badge : 'GENERAL';
                    $isChecked  = in_array((int) $s->id, $checkedIds, true);
                    $itemHay = mb_strtolower(\Illuminate\Support\Str::ascii(implode(' ', array_filter([
                        $s->regulation_code,
                        $s->category_name,
                        $s->category_name_localized,
                        $s->regulation_badge,
                    ]))));
                @endphp
                <label class="he-picker__item" data-marco="{{ $badgeClass }}" data-search="{{ $itemHay }}">
                    <input type="checkbox" name="standards[]" value="{{ $s->id }}"
                           data-code="{{ $s->regulation_code }}" data-marco="{{ $badgeClass }}"
                           {{ $isChecked ? 'checked' : '' }}>
                    <span class="he-picker__lbl">
                        <span class="badge badge-{{ $badgeClass }}">{{ $s->regulation_badge }}</span>
                        <span class="he-picker__code">{{ $s->regulation_code }}</span>
                        <span class="he-picker__cat">— {{ $s->category_name_localized }}</span>
                    </span>
                </label>
            @empty
                <div class="he-picker__none">No hay normas activas en el catálogo para ligar.</div>
            @endforelse
            <div class="he-picker__nomatch" id="hePickerNoMatch" hidden>Ninguna norma coincide con el filtro.</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var root = document.getElementById('hePickerRoot');
        if (!root) { return; }

        // Enum CERRADO validado en cliente: el data-marco viene del servidor YA validado, pero
        // el chip se vuelve a validar aquí antes de usarse como clase CSS (defensa en profundidad).
        var BADGES = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL', 'AMAZON'];
        function badgeClass(m) { return BADGES.indexOf(m) !== -1 ? m : 'GENERAL'; }

        var search    = document.getElementById('hePickerSearch');
        var selZone   = document.getElementById('hePickerSelected');
        var list      = document.getElementById('hePickerList');
        var noMatch   = document.getElementById('hePickerNoMatch');
        var facetBtns = Array.prototype.slice.call(root.querySelectorAll('.he-picker__facets .epf-chip'));
        var items     = Array.prototype.slice.call(list.querySelectorAll('.he-picker__item'));
        var boxes     = Array.prototype.slice.call(list.querySelectorAll('input[name="standards[]"]'));

        function norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        // ---- Zona de seleccionadas: SOLO textContent + clase de marco validada (nunca innerHTML con datos de BD) ----
        function renderSelected() {
            while (selZone.firstChild) { selZone.removeChild(selZone.firstChild); }

            var checked = boxes.filter(function (b) { return b.checked; });
            if (!checked.length) {
                var empty = document.createElement('span');
                empty.className = 'he-picker__empty';
                empty.textContent = 'Ninguna norma ligada todavía.';
                selZone.appendChild(empty);
                return;
            }

            checked.forEach(function (b) {
                var marco = b.getAttribute('data-marco') || 'GENERAL';
                var code  = b.getAttribute('data-code') || '';

                var chip = document.createElement('span');
                chip.className = 'he-selchip';

                var badge = document.createElement('span');
                badge.className = 'badge badge-' + badgeClass(marco);
                badge.textContent = marco;               // textContent: sin HTML
                chip.appendChild(badge);

                var codeEl = document.createElement('span');
                codeEl.textContent = code;               // textContent: dato de BD seguro
                chip.appendChild(codeEl);

                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'he-selchip__x';
                x.textContent = '×';                 // ×
                x.setAttribute('aria-label', 'Quitar ' + code);
                x.addEventListener('click', function () {
                    b.checked = false;
                    renderSelected();
                });
                chip.appendChild(x);

                selZone.appendChild(chip);
            });
        }

        // ---- Filtro del listado por texto + facetas de marco (unión dentro del marco) ----
        function activeMarcos() {
            var set = [];
            facetBtns.forEach(function (btn) {
                if (btn.getAttribute('aria-pressed') === 'true') { set.push(btn.getAttribute('data-marco')); }
            });
            return set;
        }

        function filterList() {
            var term   = norm(search ? search.value : '').trim();
            var marcos = activeMarcos();
            var shown  = 0;

            items.forEach(function (it) {
                var okText  = term === '' || (it.getAttribute('data-search') || '').indexOf(term) !== -1;
                var okMarco = marcos.length === 0 || marcos.indexOf(it.getAttribute('data-marco')) !== -1;
                var ok = okText && okMarco;
                it.classList.toggle('he-hidden', !ok);
                if (ok) { shown++; }
            });

            if (noMatch) { noMatch.hidden = shown !== 0 || items.length === 0; }
        }

        boxes.forEach(function (b) { b.addEventListener('change', renderSelected); });
        if (search) { search.addEventListener('input', filterList); }
        facetBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var on = btn.getAttribute('aria-pressed') === 'true';
                btn.setAttribute('aria-pressed', on ? 'false' : 'true');
                filterList();
            });
        });

        renderSelected();
        filterList();
    })();
</script>
@endpush
