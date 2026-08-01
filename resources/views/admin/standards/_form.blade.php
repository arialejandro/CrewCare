@php
    // $standard puede ser null (crear) o un modelo (editar). Todos los valores caen a
    // old() primero para conservar lo tecleado tras un error de validación.
    $c = $standard ?? null;

    // Enum CERRADO del marco (espejo exacto de la regla in: del controlador). NO se
    // expone is_active ni verified_* aquí: los mueve el servidor (verify/deactivate).
    $badges = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL'];
@endphp

{{-- ===== Marco y código ===== --}}
<div class="cc-group-title">
    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
    Marco y código
</div>
<div class="row g-3">
    <div class="col-12 col-md-4">
        <div class="cc-field">
            <label for="regulation_badge" class="cc-label">Marco normativo <span class="cc-req" aria-hidden="true">*</span></label>
            <select class="form-select cc-select @error('regulation_badge') is-invalid @enderror"
                    id="regulation_badge" name="regulation_badge" required>
                <option value="" disabled {{ old('regulation_badge', $c->regulation_badge ?? '') === '' ? 'selected' : '' }}>Selecciona el marco…</option>
                @foreach($badges as $b)
                    <option value="{{ $b }}" {{ old('regulation_badge', $c->regulation_badge ?? '') === $b ? 'selected' : '' }}>{{ $b }}</option>
                @endforeach
            </select>
            @error('regulation_badge')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-md-8">
        <div class="cc-field">
            <label for="regulation_code" class="cc-label">Código de la norma <span class="cc-req" aria-hidden="true">*</span></label>
            {{-- pattern espejo del regex del servidor (^[A-Za-z0-9 ./-]+$): es un filtro de UI,
                 la validación real la hace el Form Request. El código es ÚNICO en la tabla. --}}
            <input type="text" class="form-control cc-control @error('regulation_code') is-invalid @enderror"
                   id="regulation_code" name="regulation_code" value="{{ old('regulation_code', $c->regulation_code ?? '') }}"
                   maxlength="100" pattern="[A-Za-z0-9 ./-]+"
                   title="Alfanumérico, puntos, guiones, barras y espacios" required>
            @error('regulation_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Alfanumérico, puntos, guiones y barras (p. ej. <code>CSATF-B-01</code>). Debe ser único.</span>
        </div>
    </div>
</div>

{{-- ===== Categoría ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-14', 'label' => null])
    Categoría
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="cc-field">
            <label for="category_name" class="cc-label">Nombre de la categoría <span class="cc-req" aria-hidden="true">*</span></label>
            <input type="text" class="form-control cc-control @error('category_name') is-invalid @enderror"
                   id="category_name" name="category_name" value="{{ old('category_name', $c->category_name ?? '') }}"
                   maxlength="150" required>
            @error('category_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <div class="cc-field">
            <label for="category_name_en" class="cc-label">
                Nombre en inglés <span class="cc-optional">(opcional)</span>
            </label>
            <input type="text" class="form-control cc-control @error('category_name_en') is-invalid @enderror"
                   id="category_name_en" name="category_name_en" value="{{ old('category_name_en', $c->category_name_en ?? '') }}"
                   maxlength="255">
            @error('category_name_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Para consulta en inglés (se muestra cuando el idioma activo es EN).</span>
        </div>
    </div>
</div>

{{-- ===== Fuente ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'external-link', 'class' => 'cc-ico-14', 'label' => null])
    Fuente
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="cc-field">
            <label for="reference_url" class="cc-label">
                Enlace a la fuente oficial <span class="cc-optional">(opcional)</span>
            </label>
            <input type="url" inputmode="url" class="form-control cc-control @error('reference_url') is-invalid @enderror"
                   id="reference_url" name="reference_url" value="{{ old('reference_url', $c->reference_url ?? '') }}"
                   maxlength="500" autocomplete="url" placeholder="https://…">
            @error('reference_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Pega el enlace público al documento normativo (boletín CSATF, estándar OSHA, NOM STPS…).</span>
        </div>
    </div>
</div>
