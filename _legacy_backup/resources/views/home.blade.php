@extends('layouts.app')



@section('content')


@if(auth()->user()->admin)
<div class="container">
    <div class="row">
        <div class="col">
        @if(auth()->user()->encuestadiaria === 1)
            <h1 class="display-4 main-msg">Hola {{auth()->user()->name}} bienvenido al panel de administración.</h1>
        @else
        <h1 class="display-4 main-msg">Hola {{auth()->user()->name}} bienvenido al panel de administración.</h1>
        
        
        </div>
        <a class="btn btn-outline-info" href="/dailyreport">Cuestionario de salud</a>
        @endif
        
       
       
    </div>
</div>
@else
<div class="container" >
    <div class="row  justify-content-center align-items-center" style="height: 30vh;">
        <div class="col-12 d-flex align-items-center justify-content-center" style="height: 30vh;">
            @if(auth()->user()->encuestadiaria)
            <h1 class="display-4 content-wr">Gracias responder su cuestionario de salud.</h1>
            @else
            
            <h1 class="display-4 content-wr">Hola <strong>{{auth()->user()->name}} {{auth()->user()->lname}}</strong> por favor responda su cuestionario.</h1>
            </div>
            <a class="btn btn-outline-info" href="/dailyreport">Responder cuestionario de salud</a>
            @endif
    </div>
</div>
@endif


@endsection

