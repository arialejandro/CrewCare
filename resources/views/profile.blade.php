@extends('layouts.app')

{{-- CSRF: the cropper AJAX (public/js/profile.js) reads this `_token` meta by
     name. The layout exposes a differently-named `csrf-token` meta; to avoid any
     risk we KEEP the original `_token` meta here and the JS keeps reading it
     (same conservative decision as the Home). Both metas hold the same
     csrf_token(). --}}
@push('styles')
    <meta name="_token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
    {{-- Cropper.js styles (load-bearing: profile-photo cropper). --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.css"/>
@endpush

@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

<div class="profile-page container-fluid py-4" style="max-width: 1000px;">

    {{-- (2026-07-24) El alta del expediente clínico vuelve AQUÍ (redirect de
         FormulariosController@newformulario): sin este parcial, guardar el expediente —o
         chocar con uno ya registrado— no decía absolutamente nada. --}}
    @include('componentes._form-feedback')

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="cc-form-ico">
            @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Mi perfil') }}</h1>
            <div class="cc-muted small">{{ $users->name }} {{ $users->lname }}</div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===== Tarjeta visual de perfil (widget con estilos de profile.css) ===== --}}
        <div class="col-12 col-lg-5 d-flex justify-content-center">
            {{-- Avatar::url = fuente única (2026-07-24): si el archivo no está en disco cae a la
                 silueta genérica. Antes esta tarjeta pintaba el icono roto del navegador. --}}
            <div class="profile-card-2"><img src="{{ \App\Support\Avatar::url($users) }}" class="img img-fluid" alt="{{ $users->name }}">
                <div class="profile-logo-container"></div>
                <div class="profile-logo"><img src="{{ URL::asset('img/logo-cc-usrs.svg') }}" width="100" alt=""></div>
                <div class="profile-logo-client"><img src="{{ ($branding['client_logo'] ?? '') ?: URL::asset('img/redrum.png') }}" width="80" alt=""></div>
                <div class="profile-text-container"></div>
                <div class="profile-name">{{$users->name}} {{$users->lname}}</div>
                <div class="profile-username">{{ $users->departmentName() }}</div>
                <div class="profile-icons">
                    <span class="data-basic">{{ $users->positionName() }} |
                    {{ \Carbon\Carbon::parse($users->borndate)->age }}</span>
                </div>
            </div>
        </div>

        {{-- ===== Cambiar foto de perfil ===== --}}
        <div class="col-12 col-lg-7">
            <div class="cc-form-card">
                <div class="cc-form-card__head">
                    <span class="cc-form-ico">
                        @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico-20', 'label' => null])
                    </span>
                    <div class="cc-form-card__titles">
                        <h2 class="cc-form-card__title">{{ __('Foto de perfil') }}</h2>
                        <p class="cc-form-card__sub">{{ __('Sube una imagen; podrás recortarla antes de guardarla.') }}</p>
                    </div>
                </div>
                <div class="cc-form-card__body">
                    <form action="{{ route('uploadCropImage')}}" enctype='multipart/form-data' method="POST">
                        {{ csrf_field() }}
                        <div class="cc-field">
                            <label for="imgperfil" class="cc-label">{{ __('Cambia tu foto de perfil') }}</label>
                            {{-- class `image` es LOAD-BEARING: profile.js abre el recortador al cambiar este input. --}}
                            <input id="imgperfil" type="file" name="imgperfil" accept="image/*" class="form-control image cc-control">
                            <span class="cc-help">{{ __('Formatos de imagen. Se recorta a 1:1 (800×800).') }}</span>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ===== Cuestionario de salud ===== --}}
            <div class="cc-form-card">
                <div class="cc-form-card__head">
                    <span class="cc-form-ico">
                        @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-20', 'label' => null])
                    </span>
                    <div class="cc-form-card__titles">
                        <h2 class="cc-form-card__title">{{ __('Cuestionario de salud') }}</h2>
                        <p class="cc-form-card__sub">{{ __('Registro diario del expediente clínico.') }}</p>
                    </div>
                </div>
                <div class="cc-form-card__body">
                    @if(auth()->user()->encuestadiaria)
                        <div class="cc-signnote">
                            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-signnote__ico cc-ico-18', 'label' => null])
                            <span>{{ __('Gracias responder su cuestionario de salud.') }}</span>
                        </div>
                    @else
                        <p class="mb-3">{{ __('Hola') }} <strong>{{auth()->user()->name}} {{auth()->user()->lname}}</strong> {{ __('por favor responda su cuestionario.') }}</p>
                        <a class="btn btn-primary cc-cta" href="/dailyreport">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-18', 'label' => null])
                            {{ __('Responder cuestionario de salud') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>


{{-- Profile-photo cropper modal. MIGRATED Bootstrap 4 -> Bootstrap 5:
       data-dismiss  -> data-bs-dismiss
       <button class="close"><span>&times;</span></button>  ->  <button class="btn-close">
     JS show/hide ported in public/js/profile.js. --}}
<div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Corta tu foto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="img-container">
            <div class="row">
                <div class="col-md-8 imgs">
                    <img class="imgs" id="image" src="https://avatars0.githubusercontent.com/u/3456749">
                </div>
                <div class="col-md-4">
                    <div class="preview"></div>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="crop">Actualizar</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
    {{-- Load-bearing libs for this page (dossier §8):
         - Cropper.js: profile-photo cropper widget.
         Bootstrap 4 CDN CSS/JS + Popper 1.x were REMOVED (modal ported to BS5,
         which the layout already provides). The duplicate jQuery was REMOVED
         (the layout already loads jQuery). --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.js"></script>

    {{-- Expose the upload route to the external JS (which can't use Blade). --}}
    <script>
        window.uploadCropImageUrl = "{{ route('uploadCropImage') }}";
    </script>
    <script src="{{ asset('js/profile.js') }}"></script>
@endpush
