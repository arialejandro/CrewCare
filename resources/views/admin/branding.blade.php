@extends('layouts.app')
@section('content')

@push('styles')
<style>
    /* ===== Marca · "Cinematic Dark Glass" ===== */
    .bd-wrap { max-width: 880px; }

    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none;
        display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent);
        border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }

    /* Cabecera de tarjeta homologada al vidrio (antes navy horneado). */
    .bd-card .card-header {
        background:transparent; color:var(--text); font-weight:600;
        letter-spacing:.01em; border-bottom:1px solid var(--stroke);
        border-radius:var(--radius) var(--radius) 0 0;
    }

    /* Campos tokenizados */
    .bd-wrap .form-label { color:var(--text); }
    .bd-wrap .form-text { color:var(--text-muted); }
    .bd-wrap .form-control {
        background-color:var(--glass); border:1px solid var(--stroke); color:var(--text);
    }
    .bd-wrap .form-control::placeholder { color:var(--text-muted); opacity:.75; }
    .bd-wrap .form-control:focus {
        background-color:var(--bg-2); border-color:var(--brand-primary); color:var(--text);
        box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb), .22);
    }

    .bd-color-row { display:flex; align-items:center; gap:.75rem; }
    .bd-swatch-input { width:46px; height:46px; padding:0; border:1px solid var(--stroke-2); border-radius:10px; background:none; cursor:pointer; }
    .bd-hex { max-width:140px; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; text-transform:uppercase; }

    /* Marco del logo: superficie neutra para ver logos claros u oscuros. */
    .bd-logo-frame {
        width:160px; height:80px; border:1px solid var(--stroke); border-radius:12px;
        background:var(--surface-3); display:flex; align-items:center; justify-content:center; padding:.5rem;
    }

    /* Vista previa en vivo — muestra la marca sobre un panel de UI de referencia.
       Mantiene su propia paleta (--p/--s/--a) que el JS actualiza. */
    .bd-preview { --p:#ff9900; --s:#1f2937; --a:#0ea5e9;
        border:1px dashed var(--stroke-2); border-radius:14px; padding:1.1rem; background:
        repeating-conic-gradient(color-mix(in srgb, var(--text-muted) 12%, transparent) 0% 25%, transparent 0% 50%) 50% / 18px 18px; }
    .bd-pv-panel { background:var(--bg-2); border:1px solid var(--stroke); border-radius:12px; padding:1rem; }
    .bd-pv-title { color:var(--text); }
    .bd-pv-swatches { display:flex; gap:.5rem; flex-wrap:wrap; }
    .bd-pv-sw { flex:1; min-width:90px; border-radius:10px; padding:.6rem .7rem; color:#fff; font-size:.72rem; font-weight:600; }
    .bd-pv-btn { background:var(--p); color:#111; border:none; border-radius:9px; padding:.5rem .95rem; font-weight:600; font-size:.85rem; }
    .bd-pv-chip { background:var(--a); color:#fff; border-radius:999px; padding:.25rem .7rem; font-size:.72rem; font-weight:600; }
    .bd-pv-menu { margin-top:.7rem; border-radius:10px; overflow:hidden; border:1px solid var(--stroke); }
    .bd-pv-item { display:flex; align-items:center; gap:.55rem; padding:.5rem .8rem; font-size:.85rem; font-weight:500; color:var(--text-muted); }
    .bd-pv-item.is-hover { background:color-mix(in srgb, var(--p) 14%, transparent); color:var(--text); }
    .bd-pv-item.is-hover i { color:var(--p); }
    .bd-pv-item.is-active { background:var(--p); color:#1f2937; font-weight:600; }
    .bd-pv-item i { width:16px; text-align:center; color:var(--text-muted); }

    @media (max-width:767px){ .adm-title { font-size:1.15rem; } }
</style>
@endpush

<div class="container py-4 bd-wrap">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Marca</h1>
            <p class="adm-subtitle">Personaliza la identidad visual de esta instancia (super-admin).</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('settings.branding.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- Identidad --}}
        <div class="card bd-card mb-4">
            <div class="card-header">Identidad</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre de marca</label>
                        <input type="text" name="brand_name" class="form-control" value="{{ old('brand_name', $current['brand_name']) }}" placeholder="Ej: Pimienta / ENEG">
                        <div class="form-text">Reemplaza el nombre del proyecto en reportes y encabezados.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Título de la app / PWA</label>
                        <input type="text" name="app_title" class="form-control" value="{{ old('app_title', $current['app_title']) }}" placeholder="Ej: CrewCare | Pimienta">
                        <div class="form-text">Pestaña del navegador y nombre al instalar la app.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sistema de color --}}
        <div class="card bd-card mb-4">
            <div class="card-header">Sistema de color</div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Primario</label>
                        <div class="bd-color-row">
                            <input type="color" class="bd-swatch-input" data-pick="primary_color" value="{{ old('primary_color', $current['primary_color']) }}" title="Color primario">
                            <input type="text" name="primary_color" class="form-control bd-hex" data-hex="primary_color" value="{{ old('primary_color', $current['primary_color']) }}" pattern="#[0-9A-Fa-f]{6}" maxlength="7">
                        </div>
                        <div class="form-text">Acento principal: menú activo, títulos y realces de reportes.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Secundario</label>
                        <div class="bd-color-row">
                            <input type="color" class="bd-swatch-input" data-pick="secondary_color" value="{{ old('secondary_color', $current['secondary_color']) }}" title="Color secundario">
                            <input type="text" name="secondary_color" class="form-control bd-hex" data-hex="secondary_color" value="{{ old('secondary_color', $current['secondary_color']) }}" pattern="#[0-9A-Fa-f]{6}" maxlength="7">
                        </div>
                        <div class="form-text">Tinta de apoyo: textos de acento y hover del menú.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Acento</label>
                        <div class="bd-color-row">
                            <input type="color" class="bd-swatch-input" data-pick="accent_color" value="{{ old('accent_color', $current['accent_color']) }}" title="Color de acento">
                            <input type="text" name="accent_color" class="form-control bd-hex" data-hex="accent_color" value="{{ old('accent_color', $current['accent_color']) }}" pattern="#[0-9A-Fa-f]{6}" maxlength="7">
                        </div>
                        <div class="form-text">Realce/pop: chips, etiquetas y llamados a la acción.</div>
                    </div>
                </div>

                {{-- Vista previa en vivo --}}
                <div class="bd-preview mt-4">
                    <div class="bd-pv-panel">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <span class="bd-pv-title fw-bold text-uppercase" style="letter-spacing:.08em; font-size:.8rem;">Vista previa</span>
                            <span class="bd-pv-chip">Etiqueta acento</span>
                        </div>
                        <div class="bd-pv-swatches mb-3">
                            <div class="bd-pv-sw" data-sw="primary" style="background:var(--p)">Primario</div>
                            <div class="bd-pv-sw" data-sw="secondary" style="background:var(--s)">Secundario</div>
                            <div class="bd-pv-sw" data-sw="accent" style="background:var(--a)">Acento</div>
                        </div>
                        <button type="button" class="bd-pv-btn">Botón primario</button>
                        <div class="bd-pv-menu">
                            <div class="bd-pv-item is-active"><i class="fa-solid fa-gauge"></i> Ítem activo</div>
                            <div class="bd-pv-item is-hover"><i class="fa-solid fa-users"></i> Ítem al pasar el mouse</div>
                            <div class="bd-pv-item"><i class="fa-solid fa-id-badge"></i> Ítem normal</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Logo del cliente --}}
        <div class="card bd-card mb-4">
            <div class="card-header">Logo del cliente</div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <div class="bd-logo-frame">
                            <img src="{{ $current['client_logo'] ?: asset('img/redrum.png') }}" alt="Logo actual" style="max-width:100%; max-height:100%;">
                        </div>
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold">Cambiar logo</label>
                        <input type="file" name="client_logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                        <div class="form-text">PNG / JPG / WEBP / SVG, máx 2 MB. Aparece en el header, el perfil y el hero de los documentos.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Datos de producción para el formato Amazon MGM (opcionales) --}}
        <div class="card bd-card mb-4">
            <div class="card-header">Datos de producción (formato Amazon MGM)</div>
            <div class="card-body">
                <p class="cc-muted small mb-3">
                    Aparecen en el encabezado del documento oficial de scouting (Amazon MGM Studios – Risk Assessment).
                    Son opcionales: si los dejas vacíos, la compañía usa el nombre de marca y el domicilio se muestra como “—”.
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Compañía de producción</label>
                        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $current['company_name'] ?? '') }}" placeholder="Ej: Pimienta Films S.A. de C.V.">
                        <div class="form-text">“Production Company”. Vacío ⇒ se usa el nombre de marca.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Domicilio de oficina de producción</label>
                        <input type="text" name="office_address" class="form-control" value="{{ old('office_address', $current['office_address'] ?? '') }}" placeholder="Ej: Av. Reforma 222, CDMX">
                        <div class="form-text">“Production Office Address”. Vacío ⇒ se muestra “—”.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary fw-bold px-4">
                @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico me-1', 'label' => null]) Guardar marca
            </button>
        </div>
    </form>
</div>

<script>
    (function () {
        var preview = document.querySelector('.bd-preview');
        var map = { primary_color: '--p', secondary_color: '--s', accent_color: '--a' };

        function isHex(v) { return /^#[0-9A-Fa-f]{6}$/.test(v); }

        function apply(key, value) {
            if (!isHex(value)) return;
            if (preview && map[key]) preview.style.setProperty(map[key], value);
        }

        // Sincroniza selector <-> hex y refresca la vista previa.
        document.querySelectorAll('[data-pick]').forEach(function (picker) {
            var key = picker.getAttribute('data-pick');
            var hex = document.querySelector('[data-hex="' + key + '"]');
            picker.addEventListener('input', function () {
                if (hex) hex.value = picker.value.toUpperCase();
                apply(key, picker.value);
            });
            if (hex) {
                hex.addEventListener('input', function () {
                    var v = hex.value.trim();
                    if (isHex(v)) { picker.value = v; apply(key, v); }
                });
            }
            apply(key, picker.value); // estado inicial
        });
    })();
</script>
@endsection
