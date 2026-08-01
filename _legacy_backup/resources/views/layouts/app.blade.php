<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- <title>{{ config('app.name', 'Control covid') }}</title> -->
    <title>CrewCare | REDRUM</title>

    <!-- Scripts -->
    <link rel="stylesheet" href="{{ asset("css/form-register.css") }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    {{-- TinyMCE removido temporalmente (se reintroduce con el módulo de correos masivos) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>




    <script src="{{ asset('js/a2hs.js') }}"></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
    integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
    crossorigin="anonymous"></script>
<!-- -------------- Fonts -------------- -->

<link href='https://fonts.googleapis.com/css?family=Lato:400,300,300italic,400italic,700,700italic' rel='stylesheet'
          type='text/css'>
          <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

<!-- -------------- PWA -------------- -->
@laravelPWA
    <!-- PWA -->
</head>

<body>
    
  

            @include('layouts.header')

        <div class="container-fluid">
            <div class="row">
                <!-- -------------- Sidebar - Author -------------- -->
            {{-- RBAC transition (ADDITIVE): old `admin` flag kept, OR'd with the permission
                 that mirrors it for current users (`users.view`: held by super-admin, NOT by crew).
                 Identical visibility for all current users; remove the old flag only after
                 per-vertical verification against production. --}}
            @if(auth()->user()->admin || auth()->user()->can('users.view'))
                @include('layouts.sidebar')
            @else
            @endif
            <!-- -------------- Sidebar Hide Button -------------- -->
        <main class="col-lg-10">
            
                <div id="app">
                        
                         @yield('content')
                        
                        
                </div>
             </main>
        </div>


        {{-- TinyMCE removido temporalmente (se reintroduce con el módulo de correos masivos).
             El campo #MyEmail queda como <textarea> normal. --}}


        
    </body>

</html>