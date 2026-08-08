{{--
    Fila de ALTA de tripulante con cotejo CONOCER. Reutilizada en el <template> (con
    i='__IDX__', que el JS reemplaza) y para repoblar old('crew'). Las fotos no se
    repueblan (los file inputs no conservan valor); los textos sí, vía $oc.
    Requiere: $i, $oc (array, puede venir vacío), $crewRoles (array).
--}}
<div class="row g-2 align-items-end mb-3 pb-3 border-bottom crew-row">
    <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ __('Nombre') }}</label>
        <input type="text" name="crew[{{ $i }}][name]" class="form-control form-control-sm" maxlength="255" value="{{ $oc['name'] ?? '' }}">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ __('Rol') }}</label>
        <select name="crew[{{ $i }}][role]" class="form-select form-select-sm">
            <option value="">{{ __('— Rol —') }}</option>
            @foreach ($crewRoles as $r)
                <option value="{{ $r }}" @selected(($oc['role'] ?? '') === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">{{ __('Folio CONOCER') }}</label>
        <input type="text" name="crew[{{ $i }}][conocer_folio]" class="form-control form-control-sm" maxlength="120" value="{{ $oc['conocer_folio'] ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">{{ __('Clave del estándar (EC…)') }}</label>
        <input type="text" name="crew[{{ $i }}][standard_code]" class="form-control form-control-sm" maxlength="60" value="{{ $oc['standard_code'] ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">{{ __('Nombre oficial del estándar') }}</label>
        <input type="text" name="crew[{{ $i }}][standard_name]" class="form-control form-control-sm" maxlength="255" value="{{ $oc['standard_name'] ?? '' }}">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ __('Foto del certificado CONOCER (opcional)') }}</label>
        <input type="file" name="crew[{{ $i }}][cert_photo]" class="form-control form-control-sm" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">{{ __('Foto de la persona (opcional)') }}</label>
        <input type="file" name="crew[{{ $i }}][person_photo]" class="form-control form-control-sm" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
    </div>
    <div class="col-12">
        <div class="form-text">{{ __('Con folio + foto del certificado + foto de la persona, el Técnico en Atención Médica Prehospitalaria (TAMP) queda cotejado como verificado. Las fotos son opcionales; sin ambas, queda registrado sin cotejar.') }}</div>
    </div>
</div>
