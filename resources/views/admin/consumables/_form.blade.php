@php
    // $consumable puede ser null (crear) o un modelo (editar). Todos los valores caen a
    // old() primero para conservar lo tecleado tras un error de validación.
    $c = $consumable ?? null;
    $types = \App\Models\Consumable::typeLabels();
    $signalWords = ['Peligro', 'Atención'];
@endphp

{{-- ===== Identificación ===== --}}
<div class="cc-group-title">
    @include('componentes._icon', ['name' => 'package', 'class' => 'cc-ico-14', 'label' => null])
    Identificación
</div>
<div class="row g-3">
    <div class="col-12 col-md-8">
        <div class="cc-field">
            <label for="name" class="cc-label">Nombre del consumible <span class="cc-req" aria-hidden="true">*</span></label>
            <input type="text" class="form-control cc-control @error('name') is-invalid @enderror"
                   id="name" name="name" value="{{ old('name', $c->name ?? '') }}" maxlength="255" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="cc-field">
            <label for="type" class="cc-label">Tipo <span class="cc-req" aria-hidden="true">*</span></label>
            <select class="form-select cc-select @error('type') is-invalid @enderror" id="type" name="type" required>
                <option value="" disabled {{ old('type', $c->type ?? '') === '' ? 'selected' : '' }}>Selecciona…</option>
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" {{ old('type', $c->type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <div class="cc-field">
            <label for="description" class="cc-label">Descripción</label>
            <textarea class="form-control cc-control @error('description') is-invalid @enderror"
                      id="description" name="description" rows="2"
                      placeholder="Composición / uso típico en set…">{{ old('description', $c->description ?? '') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ===== Seguridad y manejo (SDS) ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-14', 'label' => null])
    Seguridad y manejo
</div>
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="hazards" class="cc-label">Peligros</label>
            <textarea class="form-control cc-control @error('hazards') is-invalid @enderror"
                      id="hazards" name="hazards" rows="3"
                      placeholder="Riesgos a la salud/seguridad (inhalación, incendio, resbalones…)">{{ old('hazards', $c->hazards ?? '') }}</textarea>
            @error('hazards')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="precautions" class="cc-label">Precauciones</label>
            <textarea class="form-control cc-control @error('precautions') is-invalid @enderror"
                      id="precautions" name="precautions" rows="3"
                      placeholder="EPP, ventilación, extintor, distancias de seguridad…">{{ old('precautions', $c->precautions ?? '') }}</textarea>
            @error('precautions')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="cc-field">
            <label for="signal_word" class="cc-label">Palabra de advertencia</label>
            <select class="form-select cc-select @error('signal_word') is-invalid @enderror" id="signal_word" name="signal_word">
                <option value="" {{ old('signal_word', $c->signal_word ?? '') === '' ? 'selected' : '' }}>—</option>
                @foreach($signalWords as $sw)
                    <option value="{{ $sw }}" {{ old('signal_word', $c->signal_word ?? '') === $sw ? 'selected' : '' }}>{{ $sw }}</option>
                @endforeach
            </select>
            @error('signal_word')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="cc-field">
            <label for="un_number" class="cc-label">Número UN</label>
            <input type="text" class="form-control cc-control @error('un_number') is-invalid @enderror"
                   id="un_number" name="un_number" value="{{ old('un_number', $c->un_number ?? '') }}"
                   maxlength="40" placeholder="Ej. UN1978">
            @error('un_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="cc-field">
            <label for="sds_url" class="cc-label">Enlace a la SDS/MSDS</label>
            <input type="url" inputmode="url" class="form-control cc-control @error('sds_url') is-invalid @enderror"
                   id="sds_url" name="sds_url" value="{{ old('sds_url', $c->sds_url ?? '') }}"
                   maxlength="2048" autocomplete="url" placeholder="https://…">
            @error('sds_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <span class="cc-help">Pega el enlace público a la hoja de datos de seguridad del proveedor.</span>
        </div>
    </div>
</div>

{{-- ===== Publicación ===== --}}
<div class="cc-group-title mt-4">
    @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico-14', 'label' => null])
    Publicación
</div>
<div class="row g-3 align-items-end">
    <div class="col-6 col-md-3">
        <div class="cc-field">
            <label for="sort_order" class="cc-label">Orden</label>
            <input type="number" class="form-control cc-control @error('sort_order') is-invalid @enderror"
                   id="sort_order" name="sort_order" value="{{ old('sort_order', $c->sort_order ?? 0) }}">
            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="cc-field">
            <div class="form-check form-switch">
                @php $activeChecked = old('is_active', $c ? $c->is_active : 1); @endphp
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                       name="is_active" value="1" {{ $activeChecked ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Activo</label>
            </div>
            <span class="cc-help">Si se desactiva, deja de ofrecerse al capturar reportes.</span>
        </div>
    </div>
</div>
