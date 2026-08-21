{{-- =============================================================================
     SIDEBAR (navegación principal) — "Cinematic Dark Glass" (mockup aprobado).
     · Secciones COLAPSABLES: solo la sección activa abierta al entrar; el encabezado
       colapsa/expande; caret que rota; contador de ítems cuando está cerrada (JS).
     · RAIL de iconos (colapsado a 74px) con FLYOUT en hover (JS clona los ítems).
     · Vidrio (tokens --glass/--stroke) + glow de marca en el ítem activo (NO indigo).
     · Command trigger "Buscar…" (⌘K) abre componentes/_command-palette.
     · Se conserva ÍNTEGRO el gateo RBAC (@can/@canany/@feature), las rutas reales, el
       parcial _icon, el offcanvas móvil (mismo patrón colapsable) y el matcher activo
       por PREFIJO. El estado rail se recuerda en localStorage ('cc-sb-rail').
     ============================================================================ --}}
@php
    $__u = \Auth::user();
    $__ini = strtoupper(mb_substr($__u->name ?? '', 0, 1) . mb_substr($__u->lname ?? '', 0, 1));
    if ($__ini === '') { $__ini = 'CC'; }
    $__role = ($__u->admin ?? false) ? __('nav.role_admin') : __('nav.role_crew');
    // (2026-07-24) La FOTO del usuario sustituye al escudo del recuadro con degradado. El
    // degradado se queda como marco: nophoto.png tiene las esquinas transparentes, así que
    // quien no ha subido foto ve la silueta genérica SOBRE el color de marca y el sidebar
    // no se rompe (84 de 92 usuarios están en ese caso). Avatar::url() comprueba el archivo
    // EN DISCO, no sólo que la columna venga llena — hay filas que apuntan a un archivo que
    // ya no existe y ésas eran justo las que pintaban el icono roto.
    $__foto = \App\Support\Avatar::url($__u);
    // FIRMAS PENDIENTES · conteo de la cola personal (badge). Cualquier firmante lo tiene, sin permiso
    // especial; se auto-limita a lo que le toca. Cacheado por-request en PendingSignatures.
    $__pendingSign = \Auth::check() ? \App\Support\PendingSignatures::countForUser((int) \Auth::id()) : 0;
    // INFOSHEETS POR AUTORIZAR · conteo de la bandeja del autorizador (badge). Se auto-limita a lo que
    // este usuario puede autorizar (canAuthorize); sin permiso especial. Cacheado por-request.
    $__pendingAuth = \Auth::check() ? \App\Support\InfosheetSigning::countForUser(\Auth::user()) : 0;
    // CONSULTA DE CONTRATOS · quién ve la entrada "Contratos" (por departamento). Producción / Oficina
    // de Producción / Contabilidad y super-admin ven todo; cada depto ve lo suyo. Ver ContractVisibility.
    $__seesContracts = \Auth::check() ? \App\Support\ContractVisibility::seesAny($__u) : false;
@endphp

<style>
    /* ---------- Columna contenedora (col-md-2) → panel de vidrio, sticky ---------- */
    .sidebar-expanded {
        padding: 0 !important;
        position: sticky; top: 60px; align-self: flex-start;
        height: calc(100vh - 60px);
        background: var(--glass);
        border-right: 1px solid var(--stroke);
        transition: flex-basis .28s var(--ease), max-width .28s var(--ease), width .28s var(--ease);
        z-index: 5;
    }
    @media screen {
        .sidebar-expanded {
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
            backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat));
        }
    }
    @supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
        .sidebar-expanded { background: var(--surface-2); }
    }

    /* Estado RAIL (colapsado): la columna encoge y el main crece (flex de Bootstrap). */
    body.cc-rail .sidebar-expanded { flex: 0 0 74px; max-width: 74px; width: 74px; }
    body.cc-rail main.cc-main      { flex: 1 1 auto; max-width: 100%; }

    /* ---------- Estructura interna ---------- */
    .cc-sb, #mobileSidebar .cc-sb {
        display: flex; flex-direction: column; height: 100%;
        font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    }
    #mobileSidebar { background: var(--surface); color: var(--text); }
    #mobileSidebar .offcanvas-header { border-bottom: 1px solid var(--border); }

    /* Marca / saludo */
    .cc-sb__brand {
        display: flex; align-items: center; gap: .6rem;
        padding: .95rem 1rem .7rem; min-height: 60px; text-decoration: none; color: var(--text);
    }
    .cc-sb__mark {
        width: 34px; height: 34px; border-radius: 10px; flex: none; display: grid; place-items: center;
        background: linear-gradient(150deg, var(--brand-primary), var(--brand-accent));
        color: var(--brand-on-primary); box-shadow: 0 6px 18px -4px var(--brand-glow);
    }
    .cc-sb__mark svg { width: 19px; height: 19px; }
    /* La foto llena el recuadro; object-fit evita que una vertical se deforme. El degradado
       de .cc-sb__mark sigue debajo y es lo que se ve por las esquinas transparentes de la
       silueta genérica. */
    .cc-sb__mark img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; display: block; }
    .cc-sb__avatar img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; display: block; }
    .cc-sb__avatar--photo { background: linear-gradient(150deg, var(--brand-primary), var(--brand-accent)); }
    .cc-sb__brand-txt { min-width: 0; line-height: 1.15; }
    .cc-sb__brand-txt b { display: block; font-size: .95rem; font-weight: 700; letter-spacing: -.01em;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cc-sb__brand-txt small { display: block; font-size: .68rem; color: var(--text-muted); }
    body.cc-rail .sidebar-expanded .cc-sb__brand-txt { opacity: 0; pointer-events: none; width: 0; }

    /* Command trigger */
    .cc-sb__cmd {
        margin: 0 .75rem .6rem; display: flex; align-items: center; gap: .55rem;
        height: 40px; padding: 0 .7rem; width: calc(100% - 1.5rem);
        border-radius: var(--radius-sm); border: 1px solid var(--stroke); background: var(--glass);
        color: var(--text-muted); font: inherit; font-size: .82rem; cursor: pointer;
        transition: border-color .18s var(--ease), color .18s var(--ease), background .18s var(--ease);
    }
    .cc-sb__cmd:hover { border-color: var(--stroke-2); color: var(--text); background: var(--glass-2); }
    .cc-sb__cmd svg { width: 16px; height: 16px; flex: none; }
    .cc-sb__cmd-kbd {
        margin-left: auto; font-family: ui-monospace, Consolas, monospace; font-size: .66rem;
        padding: 2px 6px; border-radius: 6px; background: var(--glass-2); border: 1px solid var(--stroke); color: var(--text-muted);
    }
    body.cc-rail .sidebar-expanded .cc-sb__cmd { justify-content: center; padding: 0; }
    body.cc-rail .sidebar-expanded .cc-sb__cmd-label,
    body.cc-rail .sidebar-expanded .cc-sb__cmd-kbd { display: none; }

    /* Zona scrolleable */
    .cc-sb__nav {
        flex: 1; overflow-y: auto; overflow-x: hidden; padding: .1rem .75rem .9rem;
        scrollbar-width: thin; scrollbar-color: var(--stroke-2) transparent;
    }
    .cc-sb__nav::-webkit-scrollbar { width: 8px; }
    .cc-sb__nav::-webkit-scrollbar-thumb { background: var(--stroke-2); border-radius: 8px; }

    /* ---------- Sección colapsable ---------- */
    .cc-sec { margin-bottom: 2px; position: relative; }
    .cc-sec-head {
        display: flex; align-items: center; gap: .65rem; width: 100%;
        background: none; border: 0; cursor: pointer; color: var(--text-muted); font: inherit;
        padding: .55rem .6rem; border-radius: var(--radius-sm); text-align: left; min-height: 40px;
        transition: background .16s var(--ease), color .16s var(--ease);
    }
    .cc-sec-head:hover { background: var(--glass); color: var(--text); }
    .cc-sec-head__ico { width: 18px; height: 18px; flex: none; color: var(--text-muted); transition: color .16s var(--ease); }
    .cc-sec-head__label { font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; flex: 1; }
    .cc-sec-head__caret { width: 15px; height: 15px; flex: none; color: var(--text-muted); transition: transform .28s var(--ease); }
    .cc-sec[data-open="true"] .cc-sec-head__caret { transform: rotate(90deg); }
    .cc-sec[data-open="true"] .cc-sec-head__ico { color: var(--brand-primary); }
    .cc-sec-head__count {
        font-family: ui-monospace, Consolas, monospace; font-size: .62rem; color: var(--text-muted);
        background: var(--glass-2); border: 1px solid var(--stroke); border-radius: 20px; padding: 1px 7px; margin-right: 2px;
    }
    .cc-sec[data-open="true"] .cc-sec-head__count { display: none; }

    .cc-sec-body { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .3s var(--ease); }
    .cc-sec[data-open="true"] .cc-sec-body { grid-template-rows: 1fr; }
    .cc-sec-body__inner { overflow: hidden; padding-left: .35rem; }

    /* ---------- Ítem ---------- */
    .cc-item {
        display: flex; align-items: center; gap: .7rem; padding: .5rem .7rem; margin: 2px 0;
        border-radius: var(--radius-sm); color: var(--text-muted); text-decoration: none;
        font-size: .855rem; font-weight: 500; position: relative; min-height: 40px;
        transition: background .16s var(--ease), color .16s var(--ease);
    }
    .cc-item .cc-item__ico { width: 17px; height: 17px; flex: none; color: var(--text-muted); transition: color .16s var(--ease); }
    .cc-item:hover { background: var(--glass); color: var(--text); }
    .cc-item:hover .cc-item__ico { color: var(--brand-primary); }
    .cc-item--new .cc-item__ico { color: var(--brand-primary); opacity: .85; }
    /* (2026-07-25) FIX PARPADEO DEL ÍTEM ACTIVO. El resalte era TRANSLÚCIDO (color-mix … transparent)
       y la columna es glass (backdrop-filter) con el fondo ambiente en animación `infinite` detrás:
       el backdrop en movimiento se recomponía a través de esa translucidez cada frame, así que el
       único ítem con tinte —el activo— "latía" como si se seleccionara y deseleccionara solo. Ahora
       es OPACO: misma tinta a la vista (marca 14% sobre la superficie elevada del panel), pero ya no
       muestrea el fondo en movimiento. La barra ::before de abajo es sólida; su glow es un acento fino. */
    .cc-item.cc-active {
        background: color-mix(in srgb, var(--brand-primary) 14%, var(--surface-2));
        color: var(--text); font-weight: 600;
    }
    .cc-item.cc-active .cc-item__ico { color: var(--brand-primary); }
    .cc-item.cc-active::before {
        content: ""; position: absolute; left: -.4rem; top: 7px; bottom: 7px; width: 3px; border-radius: 3px;
        background: var(--brand-primary); box-shadow: 0 0 12px 1px var(--brand-glow);
    }

    /* ---------- RAIL (solo escritorio colapsado) ---------- */
    body.cc-rail .sidebar-expanded .cc-sec-head { justify-content: center; padding: .6rem 0; }
    body.cc-rail .sidebar-expanded .cc-sec-head__label,
    body.cc-rail .sidebar-expanded .cc-sec-head__caret,
    body.cc-rail .sidebar-expanded .cc-sec-head__count { display: none; }
    body.cc-rail .sidebar-expanded .cc-sec-body { grid-template-rows: 0fr !important; }
    body.cc-rail .sidebar-expanded .cc-item span { display: none; }
    body.cc-rail .sidebar-expanded .cc-item { justify-content: center; padding: .5rem 0; }

    /* Flyout: se genera por JS y se muestra al hover de la sección en rail. */
    .cc-flyout {
        position: absolute; left: 64px; top: 0; min-width: 208px; padding: .5rem;
        opacity: 0; transform: translateX(-8px); pointer-events: none;
        transition: opacity .18s var(--ease), transform .18s var(--ease); z-index: 40;
        background: var(--bg-2); border: 1px solid var(--stroke-2); border-radius: var(--radius); box-shadow: var(--shadow);
        display: none;
    }
    @media screen {
        .cc-flyout { -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-sat)); }
    }
    .cc-flyout__title { font-size: .66rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); padding: .3rem .6rem .45rem; }
    .cc-flyout .cc-item span { display: inline; }
    .cc-flyout .cc-item { justify-content: flex-start; padding: .45rem .6rem; }
    body.cc-rail .sidebar-expanded .cc-sec:hover > .cc-flyout { display: block; opacity: 1; transform: translateX(0); pointer-events: auto; }

    /* ---------- Pie: usuario + botón rail ---------- */
    .cc-sb__foot { padding: .7rem .75rem; border-top: 1px solid var(--stroke); display: flex; align-items: center; justify-content: flex-end; gap: .6rem; }
    .cc-sb__avatar {
        width: 34px; height: 34px; border-radius: 10px; flex: none; display: grid; place-items: center;
        font-weight: 700; font-size: .8rem;
        background: color-mix(in srgb, var(--brand-primary) 22%, transparent); color: var(--brand-primary);
    }
    .cc-sb__who { min-width: 0; flex: 1; }
    .cc-sb__who b { display: block; font-size: .82rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cc-sb__who small { font-size: .68rem; color: var(--text-muted); }
    .cc-sb__rail {
        width: 34px; height: 34px; flex: none; border-radius: var(--radius-sm); border: 1px solid var(--stroke);
        background: var(--glass); color: var(--text-muted); display: grid; place-items: center; cursor: pointer;
        transition: color .16s var(--ease), border-color .16s var(--ease), background .16s var(--ease);
    }
    .cc-sb__rail:hover { color: var(--text); border-color: var(--stroke-2); background: var(--glass-2); }
    .cc-sb__rail svg { width: 18px; height: 18px; transition: transform .28s var(--ease); }
    body.cc-rail .sidebar-expanded .cc-sb__rail svg { transform: rotate(180deg); }
    body.cc-rail .sidebar-expanded .cc-sb__who { display: none; }
    body.cc-rail .sidebar-expanded .cc-sb__foot { justify-content: center; }

    /* Botón hamburguesa flotante (móvil): por ENCIMA del appbar sticky, DEBAJO del offcanvas. */
    .menu-toggle-mobile { z-index: 1035; }
</style>

{{-- Botón de menú flotante (solo móvil) — abre el offcanvas. --}}
<button
    class="btn btn-dark d-md-none position-fixed top-0 start-0 mt-2 ms-2 no-print d-flex justify-content-center align-items-center rounded-circle shadow menu-toggle-mobile"
    style="width: 44px; height: 44px;"
    data-bs-toggle="offcanvas"
    data-bs-target="#mobileSidebar"
    aria-controls="mobileSidebar"
    aria-label="{{ __('nav.menu') }}">
    @include('componentes._icon', ['name' => 'menu', 'class' => 'cc-ico', 'label' => __('nav.menu')])
</button>

{{-- =========================== SIDEBAR ESCRITORIO =========================== --}}
<div class="col-md-2 d-none d-md-block no-print sidebar-expanded">
    <div class="cc-sb cc-sb--desktop">

        <a href="/" class="cc-sb__brand">
            <span class="cc-sb__mark" aria-hidden="true">
                <img src="{{ $__foto }}" alt="">
            </span>
            <span class="cc-sb__brand-txt">
                {{-- Nombre en CRÉDITOS (ncreditos); si faltara, cae a 1er nombre + 1er apellido. NUNCA el legal aquí. --}}
                <b>{{ __('nav.greeting') }} {{ \App\Models\User::displayName($__u) }}</b>
                <small>{{ $branding['app_title'] ?? 'CrewCare' }}</small>
            </span>
        </a>

        <button type="button" class="cc-sb__cmd cc-cmd-open" aria-label="{{ __('nav.search') }}">
            @include('componentes._icon', ['name' => 'search', 'class' => '', 'label' => null])
            <span class="cc-sb__cmd-label">{{ __('nav.search') }}</span>
            <span class="cc-sb__cmd-kbd" aria-hidden="true">⌘K</span>
        </button>

        <nav class="cc-sb__nav" aria-label="{{ __('nav.menu') }}">

            {{-- ===== GENERAL ===== --}}
            <div class="cc-sec" data-open="true">
                <button type="button" class="cc-sec-head" aria-expanded="true">
                    @include('componentes._icon', ['name' => 'layout-dashboard', 'class' => 'cc-sec-head__ico', 'label' => null])
                    <span class="cc-sec-head__label">{{ __('nav.sec_general') }}</span>
                    <span class="cc-sec-head__count" aria-hidden="true"></span>
                    @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                </button>
                <div class="cc-sec-body"><div class="cc-sec-body__inner">
                    <a href="/" class="cc-item">
                        @include('componentes._icon', ['name' => 'layout-dashboard', 'class' => 'cc-item__ico', 'label' => null])
                        <span>{{ __('nav.home') }}</span>
                    </a>
                    @if(($__pendingSign ?? 0) > 0)
                    <a href="{{ route('contracts.pending.index') }}" class="cc-item">
                        @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-item__ico', 'label' => null])
                        <span>{{ __('Contratos por firmar') }}</span>
                        <span class="badge rounded-pill text-bg-primary ms-auto">{{ $__pendingSign }}</span>
                    </a>
                    @endif
                    @if(($__pendingAuth ?? 0) > 0)
                    <a href="{{ route('infosheet.pending') }}" class="cc-item">
                        @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-item__ico', 'label' => null])
                        <span>{{ __('Infosheets por autorizar') }}</span>
                        <span class="badge rounded-pill text-bg-warning ms-auto">{{ $__pendingAuth }}</span>
                    </a>
                    @endif
                    @if($__seesContracts)
                    <a href="{{ route('contracts.consult.index') }}" class="cc-item">
                        @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-item__ico', 'label' => null])
                        <span>{{ __('Contratos') }}</span>
                    </a>
                    @endif
                </div></div>
            </div>

            {{-- ===== CREW · etiqueta CONTEXTUAL: para un HOD de un solo depto muestra su departamento
                 ("Arte", "Transpo"…) en vez del genérico; para quien ve todo, genérico. ===== --}}
            @canany(['users.create', 'users.view'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'users', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ $__u->soleDepartmentName() ?? __('nav.sec_crew') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        {{-- (2026-08-07) El "nuevo" se movió a la ACCIÓN PRIMARIA de cada lista
                             (arriba a la derecha). El menú deja UNA entrada por módulo: la lista. --}}
                        @can('users.view')
                            <a href="{{ route('usuarioscrud') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.crew_list') }}</span>
                            </a>
                            <a href="/idcardscrud" class="cc-item">
                                @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.crew_badge_list') }}</span>
                            </a>
                            @can('badge.design')
                                <a href="{{ route('badge.designer') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.crew_badge_design') }}</span>
                                </a>
                            @endcan
                        @endcan
                    </div></div>
                </div>
            @endcanany

            {{-- ===== CONTABILIDAD · todo lo de PAGOS ===== --}}
            @canany(['payees.view', 'periods.view'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'wallet', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('Contabilidad') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        @can('payees.view')
                            <a href="{{ route('payees.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'wallet', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('Padrón de pago') }}</span>
                            </a>
                        @endcan
                        @can('periods.view')
                            <a href="{{ route('periods.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('Periodos de pago') }}</span>
                            </a>
                        @endcan
                    </div></div>
                </div>
            @endcanany

            {{-- ===== PRODUCCIÓN · contratos ===== --}}
            @canany(['settings.manage', 'contracts.author'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('Producción') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        @can('settings.manage')
                        {{-- Clausulados y Anexos-estáticos RETIRADOS: superados por Plantillas (categoría
                             Contrato/Anexo, con auto-llenado y tags). Rutas/controladores/datos siguen
                             vivos para contratos ya emitidos; solo se ocultó el menú. --}}
                        <a href="{{ route('contracts.route.config') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Roles de firma') }}</span>
                        </a>
                        <a href="{{ route('contracts.pending.board') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'users', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Seguimiento de firmas') }}</span>
                        </a>
                        @endcan
                        @can('contracts.author')
                        <a href="{{ route('contracts.templates.index') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Plantillas de contrato') }}</span>
                        </a>
                        @endcan
                    </div></div>
                </div>
            @endcanany

            {{-- ===== LOCACIONES ===== --}}
            @canany(['locations.view', 'locations.create', 'riskmap.issue'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_locations') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        @can('locations.view')
                            <a href="{{ route('scoutings.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.loc_scoutings') }}</span>
                            </a>
                        @endcan
                        @can('riskmap.issue')
                            <a href="{{ route('riskmaps.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'image', 'class' => 'cc-item__ico', 'label' => null])
                                <span>Mapeo de riesgos</span>
                            </a>
                        @endcan
                    </div></div>
                </div>
            @endcanany

            {{-- ===== SEGURIDAD (H&S) ===== --}}
            @canany(['dsr.view', 'dsr.create', 'hazards.view', 'hazards.create', 'injury.view', 'injury.create', 'tools.inspect', 'permits.issue', 'pae.issue', 'epi.view', 'ambulance.manage', 'ambulance.view'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_safety') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        @can('dsr.view')
                            <a href="{{ route('daily_reports.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.safety_daily_reports') }}</span>
                            </a>
                        @endcan
                        @can('hazards.view')
                            <a href="/unsafeconds" class="cc-item">
                                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.safety_unsafe_conds') }}</span>
                            </a>
                        @endcan
                        @can('hazards.view')
                            <a href="/unsafeacts" class="cc-item">
                                @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.safety_unsafe_acts') }}</span>
                            </a>
                        @endcan
                        @can('injury.view')
                            <a href="/accidents" class="cc-item">
                                @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.safety_accidents') }}</span>
                            </a>
                        @endcan
                        {{-- Inspección preventiva de herramienta: entrada PRINCIPAL (no atajo). --}}
                        @can('tools.inspect')
                            <a href="{{ route('tools.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'wrench', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.safety_inspect_tool') }}</span>
                            </a>
                        @endcan
                        {{-- Emisión de permisos de trabajo (delta #44): entrada PRINCIPAL. --}}
                        @can('permits.issue')
                            <a href="{{ route('permits.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.permits') }}</span>
                            </a>
                        @endcan
                        {{-- PAE · Plan de Atención a Emergencias (2026-08-06): entrada PRINCIPAL. --}}
                        @can('pae.issue')
                            <a href="{{ route('pae.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-item__ico', 'label' => null])
                                <span>PAE · Emergencias</span>
                            </a>
                        @endcan
                        {{-- Verificación de ambulancias (deltas #51/#52): recurso del día, docs del proveedor, acta sellada.
                             Visible para producción y safety (manage|view); transpo NO tiene ninguno. --}}
                        @canany(['ambulance.manage', 'ambulance.view'])
                            <a href="{{ route('ambulance.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-item__ico', 'label' => null])
                                <span>Ambulancias</span>
                            </a>
                        @endcanany
                        {{-- Vigilancia epidemiológica (delta #45): panel silencioso, safety + médico. --}}
                        @can('epi.view')
                            <a href="{{ route('epi.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.epi') }}</span>
                            </a>
                        @endcan
                        {{-- Reporte final de wrap: el cierre de la producción. Sólo aparece con la tabla
                             aplicada (supported()) y con permiso de lectura del DSR (mismo permiso que su
                             fuente). Va al final del bloque de seguridad porque es el documento que los
                             resume a todos. --}}
                        @can('dsr.view')
                            @if(\App\Models\WrapReport::supported())
                            <a href="{{ route('wrap.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'clipboard-check', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.safety_wrap') }}</span>
                            </a>
                            @endif
                        @endcan
                        @feature('sds_sfx')
                            {{-- REFERENCIA (doctrina): se consulta, no ocurre. Rotular con el sustantivo
                                 al frente ("SDS", "Catálogo") para que se lea distinto del panel en vivo. --}}
                            @can('sds.view')
                                <a href="{{ route('consumables.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'droplet', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>SDS / Consumibles</span>
                                </a>
                                <a href="{{ route('sfx-effects.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'flask-conical', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>Catálogo de Efectos SPFX</span>
                                </a>
                            @endcan
                            {{-- OPERACIÓN (en vivo): desde aquí se dispara de verdad. Va al final y
                                 conserva 'flame'; en modo rail el ícono es lo único que se ve. --}}
                            <a href="{{ route('sfx.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'flame', 'class' => 'cc-item__ico', 'label' => null])
                                <span>Panel SFX (en vivo)</span>
                            </a>
                        @endfeature
                    </div></div>
                </div>
            @endcanany

            {{-- ===== LLAMADOS (Call Sheet) — EN PAUSA: se reconstruye "con dirección" ===== --}}

            {{-- ===== MÉDICO ===== --}}
            @can('medical.view')
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_medical') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        {{-- Consultas: puerta ÚNICA (2026-07-31). Encuentra crew Y personas sin cuenta;
                             el alta de no-crew vive en la misma pantalla. Ya no hay item aparte de registro. --}}
                        <a href="{{ route('medicocrud') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.med_consults') }}</span>
                        </a>
                        @can('medical.consolidate')
                        <a href="{{ route('medical.bitacora') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.med_logbook') }}</span>
                        </a>
                        @endcan
                        @can('medical.materials')
                            <a href="{{ route('medical.materials') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'package', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.med_materials') }}</span>
                            </a>
                        @endcan
                    </div></div>
                </div>
            @endcan

            {{-- ===== CATÁLOGOS ===== --}}
            @canany(['catalogs.view', 'standards.view', 'hazardevents.view'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_catalogs') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        <a href="{{ route('departamentocrud') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.cat_departments') }}</span>
                        </a>
                        <a href="{{ route('positionscrud') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.cat_positions') }}</span>
                        </a>
                        <a href="{{ route('notificacioncrud') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'bell', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.cat_notifications') }}</span>
                        </a>
                        @can('standards.view')
                            <a href="{{ route('standards.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.cat_standards') }}</span>
                            </a>
                        @endcan
                        @can('hazardevents.view')
                            <a href="{{ route('hazardevents.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.cat_hazard_events') }}</span>
                            </a>
                        @endcan
                    </div></div>
                </div>
            @endcanany

            {{-- ===== ADMIN / RBAC ===== --}}
            @canany(['users.assign-role', 'roles.manage-permissions'])
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_admin') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        @can('users.assign-role')
                            <a href="{{ route('roles.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.admin_assign_roles') }}</span>
                            </a>
                        @endcan
                        @can('roles.manage-permissions')
                            <a href="{{ route('roles.permissions.edit') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.admin_permissions') }}</span>
                            </a>
                        @endcan
                    </div></div>
                </div>
            @endcanany

            {{-- ===== CONFIGURACIÓN ===== --}}
            @can('settings.manage')
                <div class="cc-sec" data-open="false">
                    <button type="button" class="cc-sec-head" aria-expanded="false">
                        @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_settings') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        <a href="{{ route('settings.branding.edit') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.settings_branding') }}</span>
                        </a>
                        <a href="{{ route('features.index') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-item__ico', 'label' => null])
                            <span>Feature Flags</span>
                        </a>
                        <a href="{{ route('emails.preview.index') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'mail', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Correos') }}</span>
                        </a>
                    </div></div>
                </div>
            @endcan

        </nav>

        <div class="cc-sb__foot">
            {{-- El pie deja SOLO el control de contraer. Nombre, foto y rol ya viven arriba
                 (saludo con el nombre en créditos) y en el perfil; repetirlos aquí sobra. --}}
            <button type="button" class="cc-sb__rail" id="ccRailBtn"
                    aria-label="{{ __('nav.collapse') }}" title="{{ __('nav.collapse') }}">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => '', 'label' => null])
            </button>
        </div>
    </div>
</div>

{{-- =========================== SIDEBAR MÓVIL (offcanvas) =========================== --}}
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="mobileSidebarLabel">{{ __('nav.menu') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('nav.menu') }}"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="cc-sb cc-sb--mobile">

            <button type="button" class="cc-sb__cmd cc-cmd-open" style="margin-top:.6rem;" aria-label="{{ __('nav.search') }}">
                @include('componentes._icon', ['name' => 'search', 'class' => '', 'label' => null])
                <span class="cc-sb__cmd-label">{{ __('nav.search') }}</span>
            </button>

            <nav class="cc-sb__nav" aria-label="{{ __('nav.menu') }}">

                {{-- ===== GENERAL ===== --}}
                <div class="cc-sec" data-open="true">
                    <button type="button" class="cc-sec-head" aria-expanded="true">
                        @include('componentes._icon', ['name' => 'layout-dashboard', 'class' => 'cc-sec-head__ico', 'label' => null])
                        <span class="cc-sec-head__label">{{ __('nav.sec_general') }}</span>
                        <span class="cc-sec-head__count" aria-hidden="true"></span>
                        @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                    </button>
                    <div class="cc-sec-body"><div class="cc-sec-body__inner">
                        <a href="/" class="cc-item">
                            @include('componentes._icon', ['name' => 'layout-dashboard', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('nav.home') }}</span>
                        </a>
                        @if(($__pendingSign ?? 0) > 0)
                        <a href="{{ route('contracts.pending.index') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Contratos por firmar') }}</span>
                            <span class="badge rounded-pill text-bg-primary ms-auto">{{ $__pendingSign }}</span>
                        </a>
                        @endif
                        @if(($__pendingAuth ?? 0) > 0)
                        <a href="{{ route('infosheet.pending') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Infosheets por autorizar') }}</span>
                            <span class="badge rounded-pill text-bg-warning ms-auto">{{ $__pendingAuth }}</span>
                        </a>
                        @endif
                        @if($__seesContracts)
                        <a href="{{ route('contracts.consult.index') }}" class="cc-item">
                            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-item__ico', 'label' => null])
                            <span>{{ __('Contratos') }}</span>
                        </a>
                        @endif
                    </div></div>
                </div>

                {{-- ===== CREW · etiqueta CONTEXTUAL (depto del HOD) ===== --}}
                @canany(['users.create', 'users.view'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'users', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ $__u->soleDepartmentName() ?? __('nav.sec_crew') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            {{-- (2026-08-07) El "nuevo" es la acción primaria de cada lista. --}}
                            @can('users.view')
                                <a href="{{ route('usuarioscrud') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'users', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.crew_list') }}</span>
                                </a>
                                <a href="/idcardscrud" class="cc-item">
                                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.crew_badge_list') }}</span>
                                </a>
                                @can('badge.design')
                                    <a href="{{ route('badge.designer') }}" class="cc-item">
                                        @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-item__ico', 'label' => null])
                                        <span>{{ __('nav.crew_badge_design') }}</span>
                                    </a>
                                @endcan
                            @endcan
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== CONTABILIDAD · pagos ===== --}}
                @canany(['payees.view', 'periods.view'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'wallet', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('Contabilidad') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            @can('payees.view')
                                <a href="{{ route('payees.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'wallet', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('Padrón de pago') }}</span>
                                </a>
                            @endcan
                            @can('periods.view')
                                <a href="{{ route('periods.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('Periodos de pago') }}</span>
                                </a>
                            @endcan
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== PRODUCCIÓN · contratos ===== --}}
                @canany(['settings.manage', 'contracts.author'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('Producción') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            @can('settings.manage')
                            {{-- Clausulados y Anexos-estáticos RETIRADOS: superados por Plantillas (categoría
                                 Contrato/Anexo, con auto-llenado y tags). Rutas/controladores/datos siguen
                                 vivos para contratos ya emitidos; solo se ocultó el menú. --}}
                            <a href="{{ route('contracts.route.config') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('Roles de firma') }}</span>
                            </a>
                            <a href="{{ route('contracts.pending.board') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('Seguimiento de firmas') }}</span>
                            </a>
                            @endcan
                            @can('contracts.author')
                            <a href="{{ route('contracts.templates.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('Plantillas de contrato') }}</span>
                            </a>
                            @endcan
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== LOCACIONES ===== --}}
                @canany(['locations.view', 'locations.create', 'riskmap.issue'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('nav.sec_locations') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            @can('locations.view')
                                <a href="{{ route('scoutings.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.loc_scoutings') }}</span>
                                </a>
                            @endcan
                            @can('riskmap.issue')
                                <a href="{{ route('riskmaps.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'image', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>Mapeo de riesgos</span>
                                </a>
                            @endcan
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== SEGURIDAD (H&S) ===== --}}
                @canany(['dsr.view', 'dsr.create', 'hazards.view', 'hazards.create', 'injury.view', 'injury.create', 'tools.inspect', 'permits.issue', 'pae.issue', 'epi.view', 'ambulance.manage', 'ambulance.view'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('nav.sec_safety') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            @can('dsr.view')
                                <a href="{{ route('daily_reports.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.safety_daily_reports') }}</span>
                                </a>
                            @endcan
                            @can('hazards.view')
                                <a href="/unsafeconds" class="cc-item">
                                    @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.safety_unsafe_conds') }}</span>
                                </a>
                            @endcan
                            @can('hazards.view')
                                <a href="/unsafeacts" class="cc-item">
                                    @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.safety_unsafe_acts') }}</span>
                                </a>
                            @endcan
                            @can('injury.view')
                                <a href="/accidents" class="cc-item">
                                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.safety_accidents') }}</span>
                                </a>
                            @endcan
                            {{-- Inspección preventiva de herramienta: entrada PRINCIPAL (no atajo). --}}
                            @can('tools.inspect')
                                <a href="{{ route('tools.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'wrench', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.safety_inspect_tool') }}</span>
                                </a>
                            @endcan
                            {{-- Emisión de permisos de trabajo (delta #44): entrada PRINCIPAL. --}}
                            @can('permits.issue')
                                <a href="{{ route('permits.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.permits') }}</span>
                                </a>
                            @endcan
                            {{-- PAE · Plan de Atención a Emergencias (2026-08-06): entrada PRINCIPAL. --}}
                            @can('pae.issue')
                                <a href="{{ route('pae.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>PAE · Emergencias</span>
                                </a>
                            @endcan
                            {{-- Verificación de ambulancias (deltas #51/#52): recurso del día, docs del proveedor, acta sellada.
                             Visible para producción y safety (manage|view); transpo NO tiene ninguno. --}}
                        @canany(['ambulance.manage', 'ambulance.view'])
                            <a href="{{ route('ambulance.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-item__ico', 'label' => null])
                                <span>Ambulancias</span>
                            </a>
                        @endcanany
                        {{-- Vigilancia epidemiológica (delta #45): panel silencioso, safety + médico. --}}
                            @can('epi.view')
                                <a href="{{ route('epi.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.epi') }}</span>
                                </a>
                            @endcan
                            {{-- Reporte final de wrap (ver bloque gemelo de arriba para el porqué). --}}
                            @can('dsr.view')
                                @if(\App\Models\WrapReport::supported())
                                <a href="{{ route('wrap.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'clipboard-check', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.safety_wrap') }}</span>
                                </a>
                                @endif
                            @endcan
                            @feature('sds_sfx')
                                {{-- REFERENCIA (doctrina): se consulta, no ocurre. Rotular con el sustantivo
                                     al frente ("SDS", "Catálogo") para que se lea distinto del panel en vivo. --}}
                                @can('sds.view')
                                    <a href="{{ route('consumables.index') }}" class="cc-item">
                                        @include('componentes._icon', ['name' => 'droplet', 'class' => 'cc-item__ico', 'label' => null])
                                        <span>SDS / Consumibles</span>
                                    </a>
                                    <a href="{{ route('sfx-effects.index') }}" class="cc-item">
                                        @include('componentes._icon', ['name' => 'flask-conical', 'class' => 'cc-item__ico', 'label' => null])
                                        <span>Catálogo de Efectos SPFX</span>
                                    </a>
                                @endcan
                                {{-- OPERACIÓN (en vivo): desde aquí se dispara de verdad. Va al final y
                                     conserva 'flame'; en modo rail el ícono es lo único que se ve. --}}
                                <a href="{{ route('sfx.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'flame', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>Panel SFX (en vivo)</span>
                                </a>
                            @endfeature
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== MÉDICO ===== --}}
                @can('medical.view')
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('nav.sec_medical') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            {{-- Consultas: puerta ÚNICA (2026-07-31). Crew + personas sin cuenta en una sola
                                 pantalla; el alta de no-crew vive ahí mismo. Sin item aparte de registro. --}}
                            <a href="{{ route('medicocrud') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.med_consults') }}</span>
                            </a>
                            @can('medical.consolidate')
                            <a href="{{ route('medical.bitacora') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.med_logbook') }}</span>
                            </a>
                            @endcan
                            @can('medical.materials')
                                <a href="{{ route('medical.materials') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'package', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.med_materials') }}</span>
                                </a>
                            @endcan
                        </div></div>
                    </div>
                @endcan

                {{-- ===== CATÁLOGOS ===== --}}
                @canany(['catalogs.view', 'standards.view', 'hazardevents.view'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('nav.sec_catalogs') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            <a href="{{ route('departamentocrud') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.cat_departments') }}</span>
                            </a>
                            <a href="{{ route('positionscrud') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.cat_positions') }}</span>
                            </a>
                            <a href="{{ route('notificacioncrud') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'bell', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.cat_notifications') }}</span>
                            </a>
                            @can('standards.view')
                                <a href="{{ route('standards.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.cat_standards') }}</span>
                                </a>
                            @endcan
                            @can('hazardevents.view')
                                <a href="{{ route('hazardevents.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.cat_hazard_events') }}</span>
                                </a>
                            @endcan
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== ADMIN / RBAC ===== --}}
                @canany(['users.assign-role', 'roles.manage-permissions'])
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('nav.sec_admin') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            @can('users.assign-role')
                                <a href="{{ route('roles.index') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'users', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.admin_assign_roles') }}</span>
                                </a>
                            @endcan
                            @can('roles.manage-permissions')
                                <a href="{{ route('roles.permissions.edit') }}" class="cc-item">
                                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-item__ico', 'label' => null])
                                    <span>{{ __('nav.admin_permissions') }}</span>
                                </a>
                            @endcan
                        </div></div>
                    </div>
                @endcanany

                {{-- ===== CONFIGURACIÓN ===== --}}
                @can('settings.manage')
                    <div class="cc-sec" data-open="false">
                        <button type="button" class="cc-sec-head" aria-expanded="false">
                            @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-sec-head__ico', 'label' => null])
                            <span class="cc-sec-head__label">{{ __('nav.sec_settings') }}</span>
                            <span class="cc-sec-head__count" aria-hidden="true"></span>
                            @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-sec-head__caret', 'label' => null])
                        </button>
                        <div class="cc-sec-body"><div class="cc-sec-body__inner">
                            <a href="{{ route('settings.branding.edit') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('nav.settings_branding') }}</span>
                            </a>
                            <a href="{{ route('features.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-item__ico', 'label' => null])
                                <span>Feature Flags</span>
                            </a>
                            <a href="{{ route('emails.preview.index') }}" class="cc-item">
                                @include('componentes._icon', ['name' => 'mail', 'class' => 'cc-item__ico', 'label' => null])
                                <span>{{ __('Correos') }}</span>
                            </a>
                        </div></div>
                    </div>
                @endcan

            </nav>
        </div>
    </div>
</div>

{{-- =============================================================================
     COMPORTAMIENTO DEL SIDEBAR (escritorio + móvil):
       1) Colapso/expansión de secciones (una abierta al entrar; toggle por click).
       2) Contadores de ítems (leídos del DOM → respetan RBAC) visibles al cerrar.
       3) Flyouts del rail (clonados de cada sección).
       4) Toggle del RAIL (body.cc-rail) recordado en localStorage.
       5) Matcher de ítem ACTIVO por PREFIJO + auto-apertura de su sección.
     ============================================================================ --}}
<script>
    (function () {
        'use strict';

        // ---- 2) Contadores + 3) flyouts (solo tiene sentido calcular flyouts en escritorio) ----
        var desktop = document.querySelector('.sidebar-expanded .cc-sb--desktop');
        var allSecs = document.querySelectorAll('.cc-sec');

        Array.prototype.forEach.call(allSecs, function (sec) {
            var items = sec.querySelectorAll('.cc-sec-body__inner .cc-item');
            var countEl = sec.querySelector('.cc-sec-head__count');
            if (countEl) { countEl.textContent = items.length; }
        });

        // Flyouts: clona el contenido de la sección para el hover en rail (solo escritorio).
        if (desktop) {
            Array.prototype.forEach.call(desktop.querySelectorAll('.cc-sec'), function (sec) {
                var label = sec.querySelector('.cc-sec-head__label');
                var inner = sec.querySelector('.cc-sec-body__inner');
                if (!inner) { return; }
                var fly = document.createElement('div');
                fly.className = 'cc-flyout';
                var title = document.createElement('div');
                title.className = 'cc-flyout__title';
                title.textContent = label ? label.textContent : '';
                fly.appendChild(title);
                // Clona cada ítem (enlaces reales → conservan href/RBAC ya aplicado).
                Array.prototype.forEach.call(inner.querySelectorAll('.cc-item'), function (it) {
                    fly.appendChild(it.cloneNode(true));
                });
                sec.appendChild(fly);
            });
        }

        // ---- 6) MEMORIA de secciones plegables en MÓVIL (localStorage) ----
        // El escritorio conserva su comportamiento (solo la activa abierta al entrar). En el
        // offcanvas móvil, en cambio, recordamos qué secciones dejó abiertas el usuario para que
        // al reabrir el menú no tenga que volver a desplegarlas. Clave = etiqueta de la sección
        // (respeta el RBAC: si una sección no se renderiza, su clave simplemente no aplica).
        var MOBILE_KEY = 'cc-sb-open-mobile';
        function secKey(sec) {
            var l = sec.querySelector('.cc-sec-head__label');
            return l ? (l.textContent || '').trim().toLowerCase() : '';
        }
        function isMobileSec(sec) { return !!sec.closest('#mobileSidebar'); }
        function loadMobileState() {
            try { return JSON.parse(localStorage.getItem(MOBILE_KEY) || '{}') || {}; }
            catch (e) { return {}; }
        }
        function saveMobileSec(sec, open) {
            if (!isMobileSec(sec)) { return; }
            var k = secKey(sec); if (!k) { return; }
            var m = loadMobileState(); m[k] = open;
            try { localStorage.setItem(MOBILE_KEY, JSON.stringify(m)); } catch (e) {}
        }
        // Restaura el estado guardado en las secciones móviles (antes del matcher activo, que
        // puede reabrir la sección de la pantalla actual por encima de lo guardado).
        (function () {
            var saved = loadMobileState();
            Array.prototype.forEach.call(document.querySelectorAll('#mobileSidebar .cc-sec'), function (sec) {
                var k = secKey(sec);
                if (k && Object.prototype.hasOwnProperty.call(saved, k)) {
                    var open = !!saved[k];
                    sec.setAttribute('data-open', open.toString());
                    var h = sec.querySelector('.cc-sec-head');
                    if (h) { h.setAttribute('aria-expanded', open.toString()); }
                }
            });
        })();

        // ---- 1) Colapso/expansión de secciones (ambas superficies) ----
        Array.prototype.forEach.call(document.querySelectorAll('.cc-sec-head'), function (head) {
            head.addEventListener('click', function () {
                var sec = head.closest('.cc-sec');
                if (!sec) { return; }
                var open = sec.getAttribute('data-open') === 'true';
                sec.setAttribute('data-open', (!open).toString());
                head.setAttribute('aria-expanded', (!open).toString());
                saveMobileSec(sec, !open);   // solo persiste en el offcanvas móvil
            });
        });

        // ---- 4) Toggle del RAIL (recordado) ----
        var railBtn = document.getElementById('ccRailBtn');
        function applyRail(on) {
            document.body.classList.toggle('cc-rail', on);
            if (railBtn) {
                var lbl = on ? @json(__('nav.expand')) : @json(__('nav.collapse'));
                railBtn.setAttribute('aria-label', lbl);
                railBtn.setAttribute('title', lbl);
            }
        }
        try {
            if (localStorage.getItem('cc-sb-rail') === '1') { applyRail(true); }
        } catch (e) {}
        if (railBtn) {
            railBtn.addEventListener('click', function () {
                var on = !document.body.classList.contains('cc-rail');
                applyRail(on);
                try { localStorage.setItem('cc-sb-rail', on ? '1' : '0'); } catch (e) {}
            });
        }

        // ---- 5) Ítem ACTIVO por PREFIJO + auto-apertura de su sección ----
        var here = location.pathname.replace(/\/+$/, '') || '/';
        var links = document.querySelectorAll('.cc-item');

        function pathOf(a) {
            try { return new URL(a.href, location.origin).pathname.replace(/\/+$/, '') || '/'; }
            catch (e) { return null; }
        }
        function matches(path) {
            if (path === null) { return false; }
            if (path === '/') { return here === '/'; }
            return here === path || here.indexOf(path + '/') === 0;
        }

        var bestLen = -1;
        Array.prototype.forEach.call(links, function (a) {
            var href = a.getAttribute('href');
            if (!href || href === '#') { return; }
            var path = pathOf(a);
            if (matches(path) && path.length > bestLen) { bestLen = path.length; }
        });

        if (bestLen < 0) { return; }
        Array.prototype.forEach.call(links, function (a) {
            var href = a.getAttribute('href');
            if (!href || href === '#') { return; }
            var path = pathOf(a);
            if (matches(path) && path.length === bestLen) {
                a.classList.add('cc-active');
                a.setAttribute('aria-current', 'page');
                // Abre la sección contenedora (y NO cierra las demás — respeta lo abierto).
                var sec = a.closest('.cc-sec');
                if (sec) {
                    sec.setAttribute('data-open', 'true');
                    var h = sec.querySelector('.cc-sec-head');
                    if (h) { h.setAttribute('aria-expanded', 'true'); }
                }
            }
        });
    })();
</script>
