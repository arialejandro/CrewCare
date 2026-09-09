{{--
    _profile-badge.blade.php — GAFETE visual de identidad del usuario (foto de fondo + logos arriba +
    nombre/departamento/puesto/edad abajo). Fuente ÚNICA compartida por /profile e /inicio (antes el
    markup estaba duplicado y divergía: la home ni protegía `borndate` null → 500 potencial).

    Layout de FLUJO robusto: la identidad es UN contenedor anclado abajo (flex column) que crece hacia
    arriba y NUNCA se encima (el diseño viejo usaba `bottom` absolutos fijos → nombre de 2 líneas o
    puesto largo se montaban y desbordaban). Estilos: public/css/form-register.css → `.profile-card-2`.

    Parámetro: $user (default auth()->user()).
--}}
@php
    $user   = $user ?? auth()->user();
    $pcDept = trim((string) $user->departmentName());
    $pcPos  = trim((string) $user->positionName());
    $pcAge  = $user->borndate ? \Carbon\Carbon::parse($user->borndate)->age : null;
    $pcClientLogo = data_get($branding ?? [], 'client_logo') ?: URL::asset('img/redrum.png');
@endphp
<article class="profile-card-2" aria-label="{{ __('Gafete de') }} {{ $user->name }} {{ $user->lname }}">
    <img class="pc-photo" src="{{ \App\Support\Avatar::url($user) }}" alt="{{ $user->name }} {{ $user->lname }}">
    <div class="pc-scrim pc-scrim--top" aria-hidden="true"></div>

    <div class="pc-logos">
        <img class="pc-logo-cc" src="{{ URL::asset('img/logo-cc-usrs.svg') }}" alt="CrewCare">
        <img class="pc-logo-client" src="{{ $pcClientLogo }}" alt="">
    </div>

    <div class="pc-identity">
        <div class="pc-top">
            @if($pcDept !== '')<span class="pc-dept">{{ $pcDept }}</span>@endif
            @if($pcAge !== null)<span class="pc-age">{{ $pcAge }} {{ __('años') }}</span>@endif
        </div>
        <div class="pc-name">{{ $user->name }} {{ $user->lname }}</div>
        @if($pcPos !== '')<div class="pc-pos">{{ $pcPos }}</div>@endif
    </div>
</article>
