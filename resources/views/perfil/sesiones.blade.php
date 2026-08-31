@extends('layouts.app')

@section('content')
@include('componentes._form-kit')

<div class="container-fluid py-4" style="max-width: 900px;">
    @include('componentes._form-feedback')

    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Sesiones activas') }}</h1>
            <div class="cc-muted small">{{ __('Dónde tienes la sesión abierta. Puedes cerrar las demás sin cambiar tu contraseña.') }}</div>
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
                <form method="POST" action="{{ route('perfil.sesiones.cerrar') }}"
                      onsubmit="return confirm('{{ __('¿Cerrar todas las demás sesiones? Tendrás que volver a iniciar sesión en esos dispositivos.') }}');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Cerrar las demás sesiones') }}</button>
                    <span class="cc-muted small ms-2">{{ __('Útil si perdiste un dispositivo.') }}</span>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-3"><a href="{{ route('perfil') }}">← {{ __('Volver al perfil') }}</a></div>
</div>
@endsection
