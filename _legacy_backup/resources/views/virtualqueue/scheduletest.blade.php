@extends('layouts.app')

@section('content')

<div class="container">
    <div class="h-screen flex-grow-1 overflow-y-lg-auto">   
    <header class="bg-surface-primary border-bottom pt-6">
            <div class="container-fluid">
                <div class="mb-npx">
                    <div class="row">
                            <!-- Title -->
                            <h1 class="display-3 text-center">Dashboard mensajes</h1>
                            <h5 class="text-left">Elige Destinatarios</h5>
                            
                            @switch($diatest->mainday)
                            @case ('2')
                            <form class="col-sm-2" title="Especial" method="post" action="{{url('/selecta/1')}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('¿Está seguro de cambiar a modo especial?');">
                                <i class="fa-solid fa-user-group"></i>Especiales
                                </button>
                                </form>
                                <form class="col-sm-2" title="Global" method="post" action="{{url('/selectglobal/1')}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('¿Esta seguro de cambiar a modo global?');">
                                <i class="fa-solid fa-globe"></i>Global
                                </button>
                            </form>
                                @break
                            @case ('3')
                                <form class="col-sm-2" title="Coordinadores" method="post" action="{{url('/selectb/1')}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('¿Está seguro de cambiar a modo coordinadores?');">
                                <i class="fa-solid fa-user-group"></i>Coord.
                                </button>
                            </form>
                            <form class="col-sm-2" title="Global" method="post" action="{{url('/selectglobal/1')}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('¿Esta seguro de cambiar a modo global?');">
                                <i class="fa-solid fa-globe"></i>Global
                                </button>
                            </form>
                            @break
                            @default
                            <form class="col-sm-2" title="Especial" method="post" action="{{url('/selecta/1')}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('¿Está seguro de cambiar a modo especial?');">
                                <i class="fa-solid fa-user-group"></i>Especiales
                                </button>
                                </form>
                                <form class="col-sm-2" title="Coordinadores" method="post" action="{{url('/selectb/1')}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('¿Está seguro de cambiar a modo coordinadores?');">
                                <i class="fa-solid fa-user-group"></i>Coord.
                                </button>
                            </form>
                            @endswitch
                    </div>
                </div>
            </div> 
    </header>
    </div>
    <main class="my-2 bg-surface-secondary">
        <div class="container-fluid">
                <!-- Card stats 
                <div class="row">
                    <div class="col-md-6 stretch-card grid-margin">
                        <div class="card bg-danger-card card-img-holder text-white">
                            <div class="card-body">
                                <img class="card-img-absolute" src="img/circle.svg">
                                <h4>No Probados <i class="fa-solid fa-triangle-exclamation float-right-card"></i></h4> 
                        
                                <span class="h3 font-bold mb-5">{{$userNoTested}}</span>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 stretch-card grid-margin">
                        <div class="card bg-success-card card-img-holder text-white">
                            <div class="card-body">
                                <img class="card-img-absolute" src="img/circle.svg">
                                <h4>Probados <i class="fa-solid fa-check-to-slot float-right-card"></i></h4> 
                                <span class="h3 font-bold mb-5">{{$userTested}}</span>
                            </div>
                        </div>
                    </div>
				</div>-->
            <div class="row">
                <div class="col-md-12 stretch-card grid-margin">
                <form title="message" method="post" action="{{url('/mymail')}}">
                    {{ csrf_field() }}
                        <textarea name="MyEmail" id="MyEmail" placeholder="{{$message->mensaje}}"></textarea>
                        <br>
                                <button type="submit" class="btn btn-success" onclick="return confirm('¿Está seguro de cambiar a modo coordinadores?');">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar Mensaje.
                            </button>
                    </form>
                </div> 
            </div>
    <div class="my-3"></div>
        <div class="row">
			<div class="col-md-8 grid-margin">
                <div class="p-10 bg-surface-secondary">
                <div class="card">
            <div class="card-header">
            @switch($diatest->mainday)
                @case ('2')
                    <h4>Destinatarios: <strong>Coordinadores</strong> </h4>
                @break
                @case ('3')  
                    <h4>Destinatarios: <strong>Especial</strong> </h4>  
                @break
                @default
                <h4>Destinatarios: <strong>Global</strong> </h4>
                @endswitch
            </div>
        <div id="usertable" class="table-responsive">
            <table class="table table-hover table-nowrap">
                <thead class="table-light">
                    <tr>
                        <th scope="col">NC</th>
                        <th scope="col">ID</th>
                        <th scope="col">F.Name</th>
                        <th scope="col">L. Name</th>
                        <th scope="col">Email</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($usuarios as $user)
                        @if($user->enfermo == 0)
                            <tr>
                        @else
                            <tr class="table-warning">
                        @endif
                        <td><a title="No Queue" class="text-danger" style="text-decoration:none;" href="{{url('/putgg/'.$user->id)}}">
                            <i class="fa-solid fa-circle-xmark"></i>
                            </a></td>
                        <th>
                        <span class="badge bg-success">{{$user->id}}</span>
                        </th>
                        <td>{{$user->name}}</td>
                        <td>{{$user->lname}}</td>
                        <td>{{$user->email}}</td>
                        
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div>
                {{ $usuarios->links() }}
            </div>
        </div>
	</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="ms-panel ms-widget ms-identifier-widget bg-secondary">
                    <div class="ms-panel-header header-mini">
                        <h6>Enviar anuncio</h6>
                    </div>
                    <div class="ms-panel-body">
                        <div class="text-center">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <p>Este modulo enviará un correo eléctronico que puede contener información privilegiada, asegurese elegir el grupo de personas y mensaje correcto</p>
                       <!-- <div><a href="{{ route('remindertest') }}" class="center col-md-10 btn btn-warning">Enviar Mensaje</a></div>-->
                        <form action="{{ route('enviarCorreoTest') }}" method="POST">
    @csrf
    <!--<label for="correo">Selecciona un correo:</label>-->
    <select class="form-select form-select-sm" name="correo">
        <option value="correos.bienvenida">Bienvenida</option>
        <option value="correos.certificados">Certificados</option>
	    <option value="correos.nuevoingreso">Nuevo Ingreso</option>
        <option value="correos.pruebasentrada">Pruebas Crew Nuevo</option>
        <option value="correos.recordatorio">Cuestionario</option>
        <option value="correos.pruebas">Pruebas General</option>
        <option value="correos.personalizado">Personalizado</option>
    </select><br>
    <button class="center col-md-10 btn btn-warning" type="submit">Enviar correo</button>
</form>
                    </div>
                    
                    </div>
                </div>
                
            
		    </div>
        </div>
        </div>
	</main>

</div>

@endsection