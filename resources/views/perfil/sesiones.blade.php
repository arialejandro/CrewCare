@extends('layouts.app')

@section('content')
@include('componentes._form-kit')
{{-- Confirmación del submit por delegación (data-confirm), sin onsubmit inline (CSP). --}}
@include('componentes._confirm-submit')

<div class="container-fluid py-4" style="max-width: 900px;">
    @include('componentes._form-feedback')

    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Sesiones activas') }}</h1>
            <div class="cc-muted small">{{ __('Dónde tienes la sesión abierta. Puedes salir de este dispositivo o cerrar las demás sin cambiar tu contraseña.') }}</div>
        </div>
    </div>

    {{-- (2026-09-12) SALIR de ESTE dispositivo. Va SIEMPRE (no depende del driver de sesión, a
         diferencia de "cerrar las demás"). Es el mismo POST /logout del botón del topbar; aquí se
         explica su diferencia con "cerrar las demás sesiones" para que no se confundan. --}}
    <div class="cc-form-card mb-3">
        <div class="cc-form-card__body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="fw-semibold">{{ __('Cerrar esta sesión') }}</div>
                <div class="cc-muted small">{{ __('Sales de ESTE dispositivo y vuelves al inicio de sesión. Tus otras sesiones siguen abiertas.') }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}"
                  data-confirm="{{ __('¿Cerrar tu sesión en este dispositivo?') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    @include('componentes._icon', ['name' => 'log-out', 'class' => 'cc-ico-16', 'label' => null])
                    {{ __('Cerrar sesión') }}
                </button>
            </form>
        </div>
    </div>

    @if (! $driverOk)
        <div class="alert alert-info">
            {{ __('El listado de sesiones se activa cuando el administrador configura la sesión en base de datos.') }}
        </div>
    @else
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                @if ($sessions->isEmpty())
                    <p class="cc-muted mb-0">{{ __('No hay sesiones registradas todavía.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Dispositivo') }}</th>
                                    <th>{{ __('IP') }}</th>
                                    <th>{{ __('Última actividad') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessions as $s)
                                    <tr>
                                        <td>
                                            {{ $s->agent }}
                                            @if ($s->current)
                                                <span class="badge bg-success">{{ __('Esta sesión') }}</span>
                                            @endif
                                        </td>
                                        <td class="mono">{{ $s->ip }}</td>
                                        <td>{{ $s->last ? $s->last->diffForHumans() : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="cc-form-card__body border-top">
                <div class="fw-semibold mb-1">{{ __('Cerrar las demás sesiones') }}</div>
                <div class="cc-muted small mb-2">{{ __('Cierra la sesión en tus OTROS dispositivos; esta sigue abierta. Útil si perdiste un dispositivo.') }}</div>
                <form method="POST" action="{{ route('perfil.sesiones.cerrar') }}"
                      data-confirm="{{ __('¿Cerrar todas las demás sesiones? Tendrás que volver a iniciar sesión en esos dispositivos.') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Cerrar las demás sesiones') }}</button>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-3"><a href="{{ route('perfil') }}">← {{ __('Volver al perfil') }}</a></div>
</div>
@endsection
