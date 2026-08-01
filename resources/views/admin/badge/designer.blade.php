@extends('layouts.app')
@section('content')
@php $accent = $branding['primary_color'] ?? '#ff9900'; @endphp

@push('styles')
{{-- Fuentes del gafete autoalojadas para que la vista previa muestre la tipografía real
     (Poppins/Montserrat/Roboto) aunque Google Fonts no responda. --}}
@include('admin.badge._screen_fonts')
<style>
    .bd-designer .card { border:1px solid var(--border); border-radius:.85rem; box-shadow:0 1px 4px rgba(16,24,40,.06); background:var(--surface); color:var(--text); }
    .bd-designer .card-header { background:var(--surface); border-bottom:1px solid var(--border); color:var(--text); font-weight:600; display:flex; align-items:center; gap:.55rem; border-radius:.85rem .85rem 0 0; }
    .bd-designer .chip { width:30px; height:30px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; color:var(--brand-on-primary); font-size:.85rem; flex:0 0 auto; }
    .bd-designer .form-label { font-size:.78rem; color:var(--text-muted); margin-bottom:.2rem; }
    /* Inputs de captura tokenizados para no cegar en dark. */
    .bd-designer .form-control, .bd-designer .form-select { background:var(--surface); color:var(--text); border-color:var(--border); }
    .bd-designer .form-control::placeholder { color:var(--text-muted); opacity:1; }
    .bd-designer .form-text { color:var(--text-muted); }
    .bd-designer .badge-stepper .btn { border-color:var(--border); color:var(--text-muted); display:inline-flex; align-items:center; justify-content:center; }
    .bd-designer .badge-stepper .btn:hover { background:var(--surface-2); color:var(--text); }
    .bd-designer .badge-stepper .cc-ico { width:1rem; height:1rem; }
    .bd-designer .badge-stepper .btn.is-double .cc-ico-2 { margin-left:-.55rem; } /* doble chevron = ±10 */
    .bd-designer .badge-stepper input { max-width:3.4rem; font-weight:700; color:var(--text); background:var(--surface-2); border-color:var(--border); }
    .bd-designer .drop { border:1.5px dashed var(--border); border-radius:.6rem; padding:.5rem .75rem; background:var(--surface-2); }
    .bd-title small { color:var(--text-muted); font-weight:400; }
    .bd-preview-card { position:sticky; top:1rem; }
    .bd-preview-stage {
        background:
            linear-gradient(45deg,var(--surface-3) 25%,transparent 25%),
            linear-gradient(-45deg,var(--surface-3) 25%,transparent 25%),
            linear-gradient(45deg,transparent 75%,var(--surface-3) 75%),
            linear-gradient(-45deg,transparent 75%,var(--surface-3) 75%);
        background-color:var(--surface-2);
        background-size:18px 18px; background-position:0 0,0 9px,9px -9px,-9px 0;
        border-radius:.7rem; padding:14px; display:flex; justify-content:center;
    }
    .bd-preview-holder { width:205px; height:327px; overflow:hidden; }
    .bd-save-bar { position:sticky; bottom:0; z-index:5; background:linear-gradient(color-mix(in srgb, var(--surface) 0%, transparent),var(--surface) 35%); padding:1rem 0 .25rem; }
</style>
@endpush

<div class="container my-4 bd-designer">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="bd-title">
            <h4 class="mb-0 d-inline-flex align-items-center gap-2"><span style="color:{{ $accent }}">@include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null])</span> Diseñar gafete</h4>
            <small>Personaliza la credencial de tu producción. Los cambios se ven en la vista previa.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('badge.template') }}" target="_blank" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico', 'label' => null]) <span>Descargar plantilla (PDF)</span>
            </a>
            <a href="{{ route('idcardscrud') }}" class="btn btn-outline-secondary btn-sm">Volver a la lista</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center gap-2">@include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null]) <span>{{ session('success') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('badge.save') }}" id="badgeForm" enctype="multipart/form-data">
        @csrf
        <div class="row g-4">
            {{-- ===== CONTROLES ===== --}}
            <div class="col-lg-7">

                {{-- Identidad --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null])</span> Identidad del proyecto</div>
                    <div class="card-body row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre del proyecto en el gafete</label>
                            <input type="text" name="project_name" maxlength="60" class="form-control js-live"
                                   value="{{ old('project_name', $tpl['project_name']) }}" placeholder="Ej. ENEG — vacío = usa el nombre de marca">
                            <div class="form-text">Vacío = usa el nombre de marca global. Útil para usar un código de proyecto y ocultar el nombre real.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peso de la fuente</label>
                            <select name="brand_weight" class="form-select form-select-sm js-live">
                                @foreach (\App\Models\BadgeTemplate::FONT_WEIGHTS as $w => $lbl)
                                    <option value="{{ $w }}" @selected((int) old('brand_weight', $tpl['brand_weight'] ?? 300) === $w)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fuente</label>
                            <select name="font_family" class="form-select form-select-sm js-live">
                                @foreach (\App\Models\BadgeTemplate::FONTS as $f)
                                    <option value="{{ $f }}" @selected(old('font_family', $tpl['font_family']) === $f)>{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color de texto</label>
                            <input type="color" name="text_color" class="form-control form-control-color form-control-sm js-live" value="{{ old('text_color', $tpl['text_color']) }}">
                        </div>
                    </div>
                </div>

                {{-- Fondo --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null])</span> Fondo de la tarjeta</div>
                    <div class="card-body">
                        <div class="drop">
                            <label class="form-label mb-1 d-inline-flex align-items-center gap-1">@include('componentes._icon', ['name' => 'upload', 'class' => 'cc-ico', 'label' => null]) <span>Subir imagen de fondo</span></label>
                            <input type="file" name="card_bg_file" id="cardBgFile" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm">
                        </div>
                        <div class="form-text mt-2">
                            @include('componentes._icon', ['name' => 'info', 'class' => 'cc-ico', 'label' => null]) Tamaño recomendado: <strong>1276 × 2032 px</strong> (vertical, 108 × 172 mm a 300 DPI).
                            Sólo se aceptan imágenes con esa proporción (±5%).
                        </div>
                        <div id="cardBgWarn" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                </div>

                {{-- Logo de producción --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico', 'label' => null])</span> Logo de producción <small class="text-muted fw-normal">(opcional)</small></div>
                    <div class="card-body row g-3">
                        <div class="col-md-12">
                            <div class="drop">
                                <label class="form-label mb-1 d-inline-flex align-items-center gap-1">@include('componentes._icon', ['name' => 'upload', 'class' => 'cc-ico', 'label' => null]) <span>Subir logo</span></label>
                                <input type="file" name="production_logo_file" id="prodLogoFile" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-control form-control-sm">
                            </div>
                            @if (! empty($tpl['production_logo']))
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="production_logo_clear" value="1" class="form-check-input" id="prodLogoClear">
                                    <label class="form-check-label small" for="prodLogoClear">Quitar el logo actual</label>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Arriba / abajo', 'name' => 'production_logo_top', 'value' => old('production_logo_top', $tpl['production_logo_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Izquierda / derecha', 'name' => 'production_logo_left', 'value' => old('production_logo_left', $tpl['production_logo_left']), 'mode' => 'horizontal', 'min' => 0, 'max' => 108])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Tamaño', 'name' => 'production_logo_width', 'value' => old('production_logo_width', $tpl['production_logo_width']), 'mode' => 'size', 'min' => 5, 'max' => 100])</div>
                    </div>
                </div>

                {{-- Nombre del proyecto (posición) --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])</span> Nombre del proyecto — posición</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Arriba / abajo', 'name' => 'brand_top', 'value' => old('brand_top', $tpl['brand_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Izquierda / derecha', 'name' => 'brand_left', 'value' => old('brand_left', $tpl['brand_left']), 'mode' => 'horizontal', 'min' => 0, 'max' => 108])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Tamaño', 'name' => 'brand_size', 'value' => old('brand_size', $tpl['brand_size']), 'mode' => 'size', 'min' => 6, 'max' => 60])</div>
                    </div>
                </div>

                {{-- Foto --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null])</span> Foto</div>
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Forma</label>
                            <select name="photo_shape" class="form-select form-select-sm js-live">
                                <option value="circle" @selected(old('photo_shape', $tpl['photo_shape']) === 'circle')>Redonda</option>
                                <option value="rounded" @selected(old('photo_shape', $tpl['photo_shape']) === 'rounded')>Esquinas suaves</option>
                                <option value="square" @selected(old('photo_shape', $tpl['photo_shape']) === 'square')>Cuadrada</option>
                            </select>
                        </div>
                        <div class="col-md-3">@include('admin.badge._stepper', ['label' => 'Tamaño', 'name' => 'photo_size', 'value' => old('photo_size', $tpl['photo_size']), 'mode' => 'size', 'min' => 5, 'max' => 100])</div>
                        <div class="col-md-3">@include('admin.badge._stepper', ['label' => 'Arriba / abajo', 'name' => 'photo_top', 'value' => old('photo_top', $tpl['photo_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-3">@include('admin.badge._stepper', ['label' => 'Izquierda / derecha', 'name' => 'photo_left', 'value' => old('photo_left', $tpl['photo_left']), 'mode' => 'horizontal', 'min' => 0, 'max' => 108])</div>
                    </div>
                </div>

                {{-- Nombre completo --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico', 'label' => null])</span> Nombre completo</div>
                    <div class="card-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Etiqueta</label>
                            <input type="text" name="label_name" maxlength="40" class="form-control form-control-sm js-live" value="{{ old('label_name', $tpl['label_name']) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="label_name_show" value="1" class="form-check-input js-live" id="labelNameShow" @checked(! isset($tpl['label_name_show']) || $tpl['label_name_show'])>
                                <label class="form-check-label small" for="labelNameShow">Mostrar etiqueta</label>
                            </div>
                        </div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Etiqueta ↕', 'name' => 'label_name_top', 'value' => old('label_name_top', $tpl['label_name_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Nombre ↕', 'name' => 'name_top', 'value' => old('name_top', $tpl['name_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Tamaño', 'name' => 'name_size', 'value' => old('name_size', $tpl['name_size']), 'mode' => 'size', 'min' => 6, 'max' => 72])</div>
                    </div>
                </div>

                {{-- Puesto --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])</span> Puesto</div>
                    <div class="card-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Etiqueta</label>
                            <input type="text" name="label_position" maxlength="40" class="form-control form-control-sm js-live" value="{{ old('label_position', $tpl['label_position']) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="label_position_show" value="1" class="form-check-input js-live" id="labelPositionShow" @checked(! isset($tpl['label_position_show']) || $tpl['label_position_show'])>
                                <label class="form-check-label small" for="labelPositionShow">Mostrar etiqueta</label>
                            </div>
                        </div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Etiqueta ↕', 'name' => 'label_position_top', 'value' => old('label_position_top', $tpl['label_position_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Puesto ↕', 'name' => 'position_top', 'value' => old('position_top', $tpl['position_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Tamaño', 'name' => 'position_size', 'value' => old('position_size', $tpl['position_size']), 'mode' => 'size', 'min' => 6, 'max' => 48])</div>
                    </div>
                </div>

                {{-- Pie --}}
                <div class="card mb-3">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])</span> Pie de gafete</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Sello CrewCare</label>
                            <select name="powered_tone" class="form-select form-select-sm js-live">
                                <option value="gris" @selected(old('powered_tone', $tpl['powered_tone'] ?? 'gris') === 'gris')>Gris (fondos claros)</option>
                                <option value="blanco" @selected(old('powered_tone', $tpl['powered_tone'] ?? 'gris') === 'blanco')>Blanco tenue (fondos oscuros)</option>
                            </select>
                        </div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Sello ↕', 'name' => 'powered_top', 'value' => old('powered_top', $tpl['powered_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                        <div class="col-md-4">@include('admin.badge._stepper', ['label' => 'Consecutivo ↕', 'name' => 'consecutive_top', 'value' => old('consecutive_top', $tpl['consecutive_top']), 'mode' => 'vertical', 'min' => 0, 'max' => 172])</div>
                    </div>
                </div>

                <div class="bd-save-bar">
                    <button type="submit" class="btn w-100 py-2 d-inline-flex align-items-center justify-content-center gap-2" style="background:{{ $accent }}; color:var(--brand-on-primary); font-weight:600;">
                        @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico', 'label' => null]) <span>Guardar plantilla</span>
                    </button>
                </div>
            </div>

            {{-- ===== PREVIEW ===== --}}
            <div class="col-lg-5">
                <div class="card bd-preview-card">
                    <div class="card-header"><span class="chip" style="background:{{ $accent }}">@include('componentes._icon', ['name' => 'eye', 'class' => 'cc-ico', 'label' => null])</span> Vista previa</div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Escala reducida. La versión real se genera al abrir el gafete o el PDF.</p>
                        @if ($sample)
                            <div class="bd-preview-stage">
                                <div class="bd-preview-holder">
                                    <div id="previewScale" style="transform: scale(0.5); transform-origin: top left; width: 108mm; height: 172mm;">
                                        @include('admin.badge._card', ['user' => $sample, 'tpl' => $tpl, 'forPdf' => false])
                                    </div>
                                </div>
                            </div>
                            <div class="text-center text-muted small mt-2">Muestra: {{ trim(($sample->name ?? '') . ' ' . ($sample->lname ?? '')) ?: 'Integrante' }}</div>
                        @else
                            <div class="alert alert-warning mb-0">No hay usuarios para previsualizar.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        var card = document.querySelector('#previewScale .gft-card');
        if (!card) return;

        function role(r) { return card.querySelector('[data-role="' + r + '"]'); }
        function val(name) {
            var el = document.querySelector('[name="' + name + '"]');
            if (!el) return null;
            if (el.type === 'checkbox') return el.checked;
            return el.value;
        }
        function radiusFor(shape) {
            if (shape === 'circle') return '50%';
            if (shape === 'rounded') return '8mm';
            return '0';
        }
        // Mismo auto-ajuste que _card (PHP): reduce el tamaño si el texto no cabe a lo ancho.
        function fitPt(text, sizePt, usableMm, factor) {
            var len = Math.max(1, (text || '').length);
            var estMm = len * factor * sizePt * 0.3528;
            if (estMm > usableMm) sizePt = sizePt * usableMm / estMm;
            return Math.round(sizePt * 10) / 10;
        }

        var els = {
            prodlogo: role('prodlogo'), brand: role('brand'), photo: role('photo'),
            labelName: role('labelName'), name: role('name'),
            labelPosition: role('labelPosition'), position: role('position'),
            powered: role('powered'), consecutive: role('consecutive')
        };

        function applyTone() {
            var t = val('powered_tone');
            var c = (t === 'blanco') ? '#ffffff' : '#565656';
            if (els.powered) {
                var pimg = els.powered.querySelector('img');
                if (pimg) pimg.src = pimg.src.replace(/logo-cc-(gris|blanco)\.png/, 'logo-cc-' + t + '.png');
                els.powered.querySelectorAll('span, div').forEach(function (e) { e.style.color = c; });
                els.powered.style.opacity = (t === 'blanco') ? '0.55' : '0.6';
            }
            if (els.consecutive) els.consecutive.style.color = c;
        }

        function apply() {
            var font = val('font_family') + ', sans-serif';
            var color = val('text_color');
            card.style.fontFamily = font;
            card.style.color = color;
            [els.brand, els.labelName, els.name, els.labelPosition, els.position].forEach(function (e) {
                if (e) { e.style.fontFamily = font; e.style.color = color; }
            });

            if (els.brand) {
                els.brand.style.top = val('brand_top') + 'mm';
                els.brand.style.left = val('brand_left') + 'mm';
                els.brand.style.fontSize = val('brand_size') + 'pt';
                els.brand.style.fontWeight = val('brand_weight');
                var pn = (val('project_name') || '').trim();
                if (pn !== '') els.brand.textContent = pn;
            }
            if (els.prodlogo) {
                els.prodlogo.style.top = val('production_logo_top') + 'mm';
                els.prodlogo.style.left = val('production_logo_left') + 'mm';
                els.prodlogo.style.width = val('production_logo_width') + 'mm';
            }
            if (els.photo) {
                els.photo.style.top = val('photo_top') + 'mm';
                els.photo.style.left = val('photo_left') + 'mm';
                els.photo.style.width = val('photo_size') + 'mm';
                els.photo.style.height = val('photo_size') + 'mm';
                els.photo.style.borderRadius = radiusFor(val('photo_shape'));
            }
            if (els.labelName) {
                els.labelName.style.top = val('label_name_top') + 'mm';
                els.labelName.textContent = val('label_name');
                els.labelName.style.display = val('label_name_show') ? 'block' : 'none';
            }
            if (els.name) { els.name.style.top = val('name_top') + 'mm'; els.name.style.fontSize = fitPt(els.name.textContent, parseFloat(val('name_size')), 96, 0.60) + 'pt'; }
            if (els.labelPosition) {
                els.labelPosition.style.top = val('label_position_top') + 'mm';
                els.labelPosition.textContent = val('label_position');
                els.labelPosition.style.display = val('label_position_show') ? 'block' : 'none';
            }
            if (els.position) { els.position.style.top = val('position_top') + 'mm'; els.position.style.fontSize = fitPt(els.position.textContent, parseFloat(val('position_size')), 98, 0.55) + 'pt'; }
            if (els.powered) els.powered.style.top = val('powered_top') + 'mm';
            if (els.consecutive) els.consecutive.style.top = val('consecutive_top') + 'mm';
            applyTone();
        }

        document.querySelectorAll('.js-live').forEach(function (el) {
            el.addEventListener('input', apply);
            el.addEventListener('change', apply);
        });

        // Steppers: ±1 (sencilla) / ±10 (doble), con límites. Handler ÚNICO reutilizado
        // por el clic del botón y por el teclado (flechas) para accesibilidad.
        function applyStep(grp, step) {
            if (!grp) return;
            var input = grp.querySelector('input[type="number"]');
            if (!input) return;
            var min = parseFloat(grp.getAttribute('data-min'));
            var max = parseFloat(grp.getAttribute('data-max'));
            var v = parseFloat(input.value || '0') + step;
            if (!isNaN(min)) v = Math.max(min, v);
            if (!isNaN(max)) v = Math.min(max, v);
            v = Math.round(v * 100) / 100;
            input.value = v;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        document.querySelectorAll('.js-step').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyStep(btn.closest('.badge-stepper'), parseFloat(btn.getAttribute('data-step')));
            });
        });
        // Teclado: con el foco en cualquier botón del grupo, ArrowUp/ArrowDown mueven ±1
        // (Shift = ±10). Reutiliza applyStep. Up/Right = incrementa; Down/Left = decrementa.
        document.querySelectorAll('.badge-stepper').forEach(function (grp) {
            grp.addEventListener('keydown', function (e) {
                var big = e.shiftKey ? 10 : 1;
                if (e.key === 'ArrowUp' || e.key === 'ArrowRight') { e.preventDefault(); applyStep(grp, big); }
                else if (e.key === 'ArrowDown' || e.key === 'ArrowLeft') { e.preventDefault(); applyStep(grp, -big); }
            });
        });

        // Fondo: validar proporción (~108×172, ±5%) antes de aceptar + preview.
        var TARGET_RATIO = 108 / 172;
        var bgFile = document.getElementById('cardBgFile');
        var bgWarn = document.getElementById('cardBgWarn');
        if (bgFile) bgFile.addEventListener('change', function () {
            var f = bgFile.files && bgFile.files[0];
            if (!f) return;
            var url = URL.createObjectURL(f);
            var img = new Image();
            img.onload = function () {
                var r = img.naturalWidth / img.naturalHeight;
                var off = Math.abs(r - TARGET_RATIO) / TARGET_RATIO;
                if (off > 0.05) {
                    bgWarn.style.display = '';
                    bgWarn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-0.15em"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Proporción incorrecta (' + img.naturalWidth + ' × ' + img.naturalHeight + ' px). Usa una imagen vertical ~1276 × 2032 px. Se quitó el archivo.';
                    bgFile.value = '';
                } else {
                    bgWarn.style.display = 'none';
                    card.style.backgroundImage = "url('" + url + "')";
                }
            };
            img.onerror = function () { bgWarn.style.display = ''; bgWarn.textContent = 'No se pudo leer la imagen.'; bgFile.value = ''; };
            img.src = url;
        });

        // Logo: preview inmediato.
        var logoFile = document.getElementById('prodLogoFile');
        if (logoFile) logoFile.addEventListener('change', function () {
            if (logoFile.files && logoFile.files[0] && els.prodlogo) {
                els.prodlogo.src = URL.createObjectURL(logoFile.files[0]);
                els.prodlogo.style.display = '';
            }
        });

        apply();
    });
</script>
@endpush

@endsection
