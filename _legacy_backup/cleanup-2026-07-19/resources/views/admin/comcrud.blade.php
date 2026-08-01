@extends('layouts.app')
@section('content')
<div>
    <div class="row ">
        <nav>
            <div class="container mt-5 px-2">
                <form class="w-100 me-3">
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                    <input class="form-control" id="search" type="search" required placeholder="Jhon Doe...">
                        &nbsp<label class="label-icon" for="search"><i class="fa fa-search"></i> </span></label>
                    </div>
                </form>
            </div>
        </nav>
    </div>
   
    <div class="p-10 bg-surface-secondary">
    <div class="card">
            <div class="card-header">
                <h4>CrewList &nbsp&nbsp</h4> <a href="/nophoto" class="btn btn-success"><i class="fa-solid fa-flag-checkered"></i> &nbsp Exportar Crewlist</a> 
            </div>
        <div id="usertable" class="table-responsive">
            <table class="table table-hover table-nowrap">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Grupo</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Apellido</th>
                        <th scope="col">Puesto</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Email</th>
                        <th scope="col">Cambiar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usuarios as $user)
                            {{-- Tinte por `enfermo` (COVID) ELIMINADO — desacople COVID (2026-06-25) --}}
                            <tr>

                        <th>
                            {{-- PASO A (2026-07-19): eliminado el @case('2') que pintaba un badge
                                 verde "Espc". Ese 2 era el falso marcador de "médico" (botón
                                 "Convertir a Médico"), y encima la etiqueta era incoherente con
                                 la UI que lo llamaba "Médico". El valor está neutralizado en BD
                                 (2 → NULL) y ya no tiene escritor: nadie puede volver a caer ahí. --}}
                            @switch ($user->daytest)
                                @case ('3')
                                    <span class="badge bg-danger">Cord</span>
                                @break
                                    @default
                                    <span class="badge bg-secondary">Glob</span>
                                @endswitch
                        </th>
                        <td>{{$user->name}}</td>
                        <td>{{$user->lname}}</td>
                        <td>{{$user->puestodepartamento}}</td>
                        <td>{{$user->phone}}</td>
                        <td>{{$user->email}}</td>
                        
                        <td>
                        {{-- PASO A (2026-07-19): eliminados los 3 formularios que posteaban a
                             /putgb/ (grupo "B" = el falso "médico") y la rama @case('2'), ya
                             inalcanzable tras neutralizar el dato.

                             OJO — DEFECTO PREEXISTENTE, NO INTRODUCIDO AQUÍ: los formularios que
                             quedan postean a url('/putga/…') y url('/putgg/…'), que son NOMBRES
                             de ruta, no URIs. Las URIs reales son /putadm y /putsup
                             (routes/web.php), así que estos botones devuelven 404 desde hace
                             tiempo. Esta pantalla (/comcrud) además está huérfana: no hay enlace
                             a ella desde el sidebar ni desde ninguna vista. Se deja como estaba
                             para no ampliar el alcance del Paso A. --}}
                        @switch($user->daytest)
                                @case ('3')
                            <form class="dropdown-item col-2" title="GroupG" method="post" action="{{url('/putgg/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit col-2" class="btn btn-outline-dark"onclick="return confirm('User will switch to group A');">
                                <i class="fa-solid fa-g"></i>
                                </button>
                            </form>
                            @break
                            @default
                            <form class="dropdown-item col-2" title="GroupA" method="post" action="{{url('/putga/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit col-2" class="btn btn-outline-dark"onclick="return confirm('User will switch to group A');">
                                <i class="fa-solid fa-a"></i>
                                </button>
</form>
                            @endswitch
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div>
                {!! $usuarios->links() !!} 
            </div>
        </div>
    </div>
</div>
<script>
    $( document ).ready(function() {
        $( "#search" ).keyup(function() {
            valor = document.getElementById("search").value;
            console.log(valor);
            if (valor === "") {
                console.log("entro al vacio");
                valor = "vacio";
            }
                fetch('/searchusers/'+valor+'/?page=1',{
                    method: 'get'
                }).then(function(response){
                    return response.text();
                }).then(function(htmlContent){
                    if(htmlContent === ""){

                        htmlContent = '<h4 class="center">No se encontraron usuario.</h4>';
                        $('#usertable').html(htmlContent);

                    }else{
                        $('#usertable').html(htmlContent);
                    }
                }).catch(function(err){
                    console.log(err);
                })
            
        });
    });

</script>
@endsection