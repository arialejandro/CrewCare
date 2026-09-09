{{-- Fila compartida de la lista de gafetes (idcardscrud + búsqueda AJAX). Espera $user.
     Homologada al lenguaje de Crew List: avatar con reserva de dimensiones + fallback de
     iniciales, pills de estado tokenizadas, acciones icon-only con aria-label, y data-attrs
     (data-has-photo / data-printed) para los chips-filtro. cc-stack: cada td lleva data-label. --}}
@php
    // Avatar::has comprueba el archivo EN DISCO. Aquí no es cosmético: data-has-photo alimenta
    // el chip-filtro "con foto / sin foto" con el que se decide a quién se le imprime gafete,
    // y una columna llena que apunta a un archivo borrado contaba como foto y salía en blanco.
    $hasPhoto = \App\Support\Avatar::has($user) && $user->imgperfil !== 'nofoto';
    $printed = (int) ($user->badge_print_count ?? 0) > 0;
    $credits = trim((string) ($user->ncreditos ?? ''));
    // Nombre PRINCIPAL = nombre corto (1ª palabra + 1er apellido). Aquí NO usamos displayName
    // porque el crédito ya se muestra de subtítulo abajo → se duplicaría. Ver User::shortName.
    $displayName = \App\Models\User::shortName($user);
    $initials = strtoupper(mb_substr($user->name ?? '', 0, 1)) . strtoupper(mb_substr($user->lname ?? '', 0, 1));
    $zone = \App\Models\User::departmentNameFor($user->id ?? null, $user->zone ?? null) ?? '';
@endphp
<tr data-has-photo="{{ $hasPhoto ? 1 : 0 }}" data-printed="{{ $printed ? 1 : 0 }}">
    <td data-label="{{ __('listas.integrante') }}">
        <div class="d-flex align-items-center gap-3">
            @if ($hasPhoto)
                <img src="{{ \App\Support\Avatar::url($user) }}" alt="" class="crew-avatar rounded-circle">
            @else
                <span class="crew-avatar crew-avatar-initials rounded-circle d-inline-flex align-items-center justify-content-center">
                    @if (trim($initials) !== '')
                        {{ $initials }}
                    @else
                        @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico', 'label' => null])
                    @endif
                </span>
            @endif
            <div class="crew-name-cell">
                <span class="crew-name d-block">{{ $displayName ?: '—' }}</span>
                @if ($credits !== '')
                    <span class="crew-sub d-block small d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null])
                        {{ $credits }}
                    </span>
                @endif
            </div>
        </div>
    </td>
    <td data-label="{{ __('listas.depto') }}" class="text-muted">{{ $zone ?: '—' }}</td>
    <td data-label="{{ __('listas.estado') }}">
        @if ($hasPhoto)
            <span class="crew-pill crew-pill--ok">@include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null]) {{ __('listas.con_foto') }}</span>
        @else
            <span class="crew-pill crew-pill--warn">@include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null]) {{ __('listas.sin_foto') }}</span>
        @endif
        @if ($printed)
            <span class="crew-pill crew-pill--muted">@include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico', 'label' => null]) {{ __('listas.impreso') }}</span>
        @else
            <span class="crew-pill crew-pill--info">@include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico', 'label' => null]) {{ __('listas.pendiente') }}</span>
        @endif
    </td>
    <td class="text-end">
        <div class="crew-actions">
            <a href="{{ url('/idcard/' . $user->id) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('listas.ver_gafete') }}" title="{{ __('listas.ver_gafete') }}">
                @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null])
            </a>
            <a href="{{ url('/idcard/' . $user->id . '/pdf') }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('listas.descargar_pdf') }}" title="{{ __('listas.descargar_pdf') }}">
                @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico', 'label' => null])
            </a>
            @if ($printed)
                <form method="POST" action="{{ url('/uncheckgft/' . $user->id) }}" class="d-inline">@csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('listas.desmarcar_impreso') }}" title="{{ __('listas.desmarcar_impreso') }}">
                        @include('componentes._icon', ['name' => 'x', 'class' => 'cc-ico', 'label' => null])
                    </button>
                </form>
            @else
                <form method="POST" action="{{ url('/checkgft/' . $user->id) }}" class="d-inline">@csrf
                    <button type="submit" class="btn btn-sm btn-success" aria-label="{{ __('listas.marcar_impreso') }}" title="{{ __('listas.marcar_impreso') }}">
                        @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico', 'label' => null])
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
