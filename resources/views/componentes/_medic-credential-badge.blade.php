{{--
    _medic-credential-badge — estado de la cédula profesional de un médico, en chip.

    Variables:
      $credential  App\Models\MedicCredential|null  La ficha de cédula. Si viene null (o ni
                   siquiera está definida) este parcial NO PINTA NADA.
      $showNumber  bool  Opcional, default false. Si es true, antes del chip pinta el número
                   de cédula en monoespaciada.

    Uso:
      @include('componentes._medic-credential-badge', ['credential' => $cred])
      @include('componentes._medic-credential-badge', ['credential' => $cred, 'showNumber' => true])

    DEPENDENCIA DE ESTILOS: este parcial NO trae <style>. Las clases .cc-chip, .cc-chip-ok y
    .cc-chip-warn las define la PANTALLA ANFITRIONA (hoy van inline en las vistas que ya usan
    chips, p. ej. admin/standards/index). Si lo incluyes en una vista nueva, asegúrate de que
    esas tres clases existan ahí o el chip saldrá sin formato.
--}}

{{-- Degradación total: la mayoría de las pantallas que incluyen esto muestran usuarios que
     NO son médicos y por tanto no tienen ficha. Silencio, no hueco ni error. --}}
@if(! empty($credential))

    @php
        $showNumber = isset($showNumber) ? (bool) $showNumber : false;

        // PHP 7.4: optional() en vez de nullsafe (?-> es PHP 8). verified_at puede ser null
        // aunque isVerified() sea true si el sello vino de un origen sin fecha.
        $verifiedOn = optional($credential->verified_at)->format('d/m/Y');
    @endphp

    @if($showNumber && filled($credential->cedula))
        <code>{{ $credential->cedula }}</code>
    @endif

    {{-- Se consultan los MÉTODOS del modelo, nunca las columnas crudas.

         SIN @else A PROPÓSITO (mismo patrón que admin/standards/index.blade.php:264-281):
         si los dos predicados son falsos no se pinta chip alguno. Ese es justamente el caso
         en que el modelo hace NO-OP porque la tabla todavía no existe en la BD — y un chip
         "desconocido" ahí sería ruido, o peor, una afirmación sobre algo que no se consultó.
         Ningún chip = ninguna afirmación. --}}
    @if($credential->isPendingVerification())
        <span class="cc-chip cc-chip-warn"
              title="Nadie ha cotejado este número contra el Registro Nacional de Profesionistas de la SEP. NO debe darse por válido.">
            @include('componentes._icon', ['name' => 'alert-triangle'])
            Cédula sin verificar
        </span>
    @elseif($credential->isVerified())
        <span class="cc-chip cc-chip-ok"
              title="Número cotejado contra el Registro Nacional de Profesionistas de la SEP{{ $verifiedOn !== null ? ' el ' . $verifiedOn : '' }}.">
            @include('componentes._icon', ['name' => 'check'])
            Cédula verificada
        </span>
    @endif

@endif
