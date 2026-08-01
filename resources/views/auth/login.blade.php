<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Control covid') }}</title>

    <!-- Scripts -->
    
    <link rel="stylesheet" href="{{ asset("css/login.css") }}">
    <link rel="stylesheet" href="{{ asset("css/form-register.css") }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
    integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
    crossorigin="anonymous"></script>
<!-- -------------- Fonts -------------- -->
<link rel='stylesheet' type='text/css' href='http://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700'>
<link href='https://fonts.googleapis.com/css?family=Lato:400,300,300italic,400italic,700,700italic' rel='stylesheet'
          type='text/css'>
          <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
</head>

<body>
<div class="wrapper-login">
    <div class="col s12 m12">
            <div class="login-box">
            
                <div class="login-snip">
                    <div class="d-flex justify-content-end">
                        @include('layouts._lang-switch')
                    </div>
                    <div class="d-flex justify-content-center">
                        <img src="{{ URL::asset('img/logo-cc-login.svg') }}"  width="150">
                    </div>
                    <div class="d-flex justify-content-center">
                        <h4 class="content-login">{{ __('auth_ui.subtitle') }}</h4>
                    </div>
                    <div class="login-space">
                        <div class="login">
                        <form method="POST" action="{{ route('login') }}">
                                    @csrf
                            <div class="group"> <label for="email" class="label">{{ __('auth_ui.email') }}</label> <input id="email" type="email" class="input" placeholder="{{ __('auth_ui.email_ph') }}" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus> </div>
                            <div class="group"> <label for="password" class="label">{{ __('auth_ui.password') }}</label> <input id="password" type="password" class="input" name="password" required autocomplete="current-password" placeholder="{{ __('auth_ui.password_ph') }}"> </div>
                            <div class="group"> <button type="submit" class="button">{{ __('auth_ui.sign_in') }} <i class="fa-solid fa-right-to-bracket"></i> </button> </div>
                            <div class="hr"></div>
                                <div class="foot"> <a href="{{ route('password.request') }}">{{ __('auth_ui.forgot_password') }}</a> </div>
                                <div class="foot"> {{ __('auth_ui.copyright') }} </div>
                        </div>
                    </div>
                </div>
            
        </div>
    </div>
</div>

</body>

</html>
 
 
    