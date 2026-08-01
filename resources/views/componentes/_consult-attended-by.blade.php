{{-- ============================================================================================
     QUIÉN ATENDIÓ + estado de su cédula — COMPONENTE COMPARTIDO (2026-07-25).
     Antes vivía inline en historiamr; ahora lo comparten el historial de crew, el de paciente sin
     cuenta y el documento por consulta (una sola fuente para la identidad del médico).

     Manda el SNAPSHOT congelado al crear la consulta (medic_name/medic_cedula/medic_cedula_verified):
     refleja quién atendió y el estado de su cédula EN ESE MOMENTO, y es lo que cubre el sello. El
     badge EN VIVO (mapa $medicos) es SÓLO fallback para consultas anteriores al snapshot.

     Requiere que el host incluya componentes._form-kit (clases .cc-chip). Contrato:
       $consulta = App\Models\cmedic ; $medicos = colección id→User (opcional, para el fallback).
     ============================================================================================ --}}
@php
    $hasSnap = filled($consulta->medic_name) || filled($consulta->medic_cedula);
    $mv = $consulta->medic_cedula_verified;
    $mapaMed = $medicos ?? collect();
@endphp
@if($hasSnap)
    {{ $consulta->medic_name ?: '—' }}
    <span class="d-block mt-1">
        @if(filled($consulta->medic_cedula))<code>{{ $consulta->medic_cedula }}</code>@endif
        @if($mv !== null && (int) $mv === 1)
            <span class="cc-chip cc-chip-ok" title="{{ __('Cédula cotejada al momento de atender.') }}">@include('componentes._icon', ['name' => 'check']) {{ __('Cédula verificada') }}</span>
        @elseif($mv !== null)
            <span class="cc-chip cc-chip-warn" title="{{ __('El médico no tenía la cédula cotejada al momento de atender.') }}">@include('componentes._icon', ['name' => 'alert-triangle']) {{ __('Cédula sin verificar') }}</span>
        @endif
    </span>
@else
    @php
        // Fallback en vivo (consultas previas al snapshot). Guard obligatorio: sin el SQL de la cédula
        // la relación consultaría una tabla inexistente y reventaría el expediente.
        $medico  = $mapaMed->get($consulta->created_by_id);
        $medCred = ($medico && \App\Models\MedicCredential::supportsCredentials()) ? $medico->medicCredential : null;
    @endphp
    @if($medico)
        {{ $medico->fullName() }}
        <span class="d-block mt-1">
            @include('componentes._medic-credential-badge', ['credential' => $medCred, 'showNumber' => true])
        </span>
    @else
        <span class="cc-muted">—</span>
    @endif
@endif
