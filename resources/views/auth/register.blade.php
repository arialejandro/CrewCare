@extends('layouts.app')



@section('content')

<script type="text/javascript" src="js/departamentosdinamicos.js"></script>

<div class="container">

    <h4 class="font-h center">Iniciar Registro</h4>

    <hr class="hr-color">

</div><br>

<div class="container">

    <div class="row">

        <div class="col m4">

        </div>

        <div class="col s12 m10 center">

            <div class="card login-custom">

                <div class="row">

                    <div class="card-content">

                        <h4>Regístrate</h4>

                        <h6 class="login-margin">¡Ya tengo una cuenta!</h6>

                        <a class="waves-effect blue-grey btn-small m6 s6" href="{{ route('login') }}">Iniciar sesión</a>

                        <div class="login-margin"></div>

                        <form method="POST" action="{{ route('register') }}">

                            @csrf

                            <div class="input-field">

                                <input id="name" type="text" class="margenesform validate @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                <label for="name" class="margenesform leftform">{{ __('Nombre completo') }}</label>

                                @error('name')

                                <span class="margenesform" role="alert">

                                    <strong>{{ $message }}</strong>

                                </span>

                                @enderror

                            </div>

                            <div class="input-field">

                                <input id="age" type="number" class="margenesform validate @error('age') is-invalid @enderror" name="age" value="{{ old('age') }}" required autocomplete="age" autofocus>

                                <label for="age" class="margenesform leftform">{{ __('Edad') }}</label>

                                @error('age')

                                <span class="" role="alert">

                                    <strong>{{ $message }}</strong>

                                </span>

                                @enderror

                            </div>
                            <div class="input-field">

                                <input id="email" type="email" class="margenesform validate @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                                <label for="email" class="margenesform leftform">{{ __('E-Mail') }}</label>

                                @error('email')

                                <span class="" role="alert">

                                    <strong>{{ $message }}</strong>

                                </span>

                                @enderror

                            </div>

                            <div class="input-field">

                                <input id="password" type="password" class="margenesform validate @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                                <label for="password" class="margenesform leftform">{{ __('Password') }}</label>

                                @error('password')

                                <span class="" role="alert">

                                    <strong>{{ $message }}</strong>

                                </span>

                                @enderror

                            </div>

                            <div class="input-field">

                                <input id="password-confirm" type="password" class="margenesform validate " name="password_confirmation" required autocomplete="new-password">

                                <label for="password-confirm" class="margenesform leftform">{{ __('Confirma Password') }}</label>

                            </div><br>

                            <div>

                                <button type="submit" style="top: -10px;" class="margenesform leftform waves-effect blue-grey btn-small left col m12 s12">

                                    {{ __('Registrarse') }}

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

        <div class=" col m3 ">

        </div>

    </div>

</div>

<!-- <script>

    document.addEventListener('DOMContentLoaded', () => {

        fetch('http://127.0.0.1:8000/selectdepartamentos',{

            method: 'get'

        }).then(function(response){

            return response.text();

        }).then(function(htmlContent){

            console.log(htmlContent);

            $('#selectalldepto').append(htmlContent);

        }).catch(function(err){

            console.log(err);

        });

    });

</script> -->

<script>

    document.addEventListener('DOMContentLoaded', function() {

        var elems = document.querySelectorAll('select');

        var instances = M.FormSelect.init(elems);

    });

</script>

@endsection