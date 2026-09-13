@extends('layouts.app')

@section('content')
@include('componentes._form-kit')

<div class="container-fluid py-4" style="max-width: 760px;">
    @include('componentes._form-feedback')

    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'mail', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Salidas por WhatsApp') }}</h1>
            <div class="cc-muted small">{{ __('Canal opcional para registrar salidas desde el grupo.') }}</div>
        </div>
    </div>

    {{-- 🔴 AVISO: capa no verificada. --}}
    <div class="alert alert-warning">
        <strong>{{ __('Capa NO verificada.') }}</strong>
        {{ __('Se escribió contra la documentación de Meta, sin número y sin tocar la API real. Antes de encenderla hay que probar el webhook, la firma y la respuesta contra la documentación vigente. Mientras el flag esté apagado, el webhook responde 404.') }}
        <div class="small mt-1">{{ __('Estado del flag') }}: <strong>{{ $enabled ? __('ENCENDIDO') : __('APAGADO') }}</strong> ({{ __('se enciende en Feature Flags') }}).</div>
    </div>

    <div class="cc-form-card mb-3">
        <div class="cc-form-card__body">
            <div class="fw-semibold mb-1">{{ __('URL del webhook') }}</div>
            <div class="cc-muted small mb-2">{{ __('Configúrala en la app de Meta (con el verify token de abajo).') }}</div>
            <code class="d-block p-2" style="background: var(--cc-code-bg, #f1f5f9); border-radius: .4rem; word-break: break-all;">{{ $webhook_url }}</code>
        </div>
    </div>

    <form method="POST" action="{{ route('outs.whatsapp.settings.save') }}">
        @csrf
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="mb-3">
                    <label class="form-label small mb-1">{{ __('Verify token') }}</label>
                    <input type="text" name="verify_token" class="form-control form-control-sm" value="{{ old('verify_token', $verify_token) }}" autocomplete="off">
                    <div class="cc-muted small mt-1">{{ __('Cadena que eliges tú; debe coincidir con la que pongas en Meta.') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1">{{ __('Phone number ID') }}</label>
                    <input type="text" name="phone_number_id" class="form-control form-control-sm" value="{{ old('phone_number_id', $phone_number_id) }}" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1">{{ __('App secret') }}</label>
                    <input type="password" name="app_secret" class="form-control form-control-sm" placeholder="{{ $has_secret ? '•••••• ('.__('guardado').')' : '' }}" autocomplete="off">
                    <div class="cc-muted small mt-1">{{ __('Verifica la firma del webhook. Déjalo en blanco para conservar el actual.') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1">{{ __('Access token') }}</label>
                    <input type="password" name="access_token" class="form-control form-control-sm" placeholder="{{ $has_token ? '•••••• ('.__('guardado').')' : '' }}" autocomplete="off">
                    <div class="cc-muted small mt-1">{{ __('Para contestar por la Graph API. Déjalo en blanco para conservar el actual.') }}</div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Guardar credenciales') }}</button>
            </div>
        </div>
    </form>

    <div class="cc-muted small mt-3">
        🔴 {{ __('Las credenciales se guardan en texto en la base (no es un gestor de secretos). Cuando se active de verdad, evaluar cifrado en reposo o variables de entorno. Ver docs/outs-whatsapp-runbook.md.') }}
    </div>
</div>
@endsection
