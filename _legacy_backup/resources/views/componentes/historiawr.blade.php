@extends('layouts.app')



@section('content')

<hr class="hr" />
<h1 class="display-4 text-center">Historial Médico</h1>
<hr class="hr" />
<h1 class="text-center">{{$datos->name}} {{$datos->lname}} {{$datos->lname2}}</h1>
<div class="row">
    <div class="col-6">
        <h2 style="text-align: right;"></h2>
    </div>
    <div class="col-6">
        <h6>{{$datos->puestodepartamento}}</h6>
    </div>
</div>
<!--Inf. General-->
@foreach ($usuario as $user)
<div class="accordion" id="accordionExample">
  <div class="card">
    <div class="card-header" id="headingOne">
      <h5>Generales</h5>
    </div>
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
        <div class="card-body">
            <div class="row"> 
                <div class="col-4">
                    <p><strong>Tipo de sangre:</strong> {{$user->blod_type}}</p>
                </div>
                <div class="col-2">
                    <p><strong>Peso:</strong> {{$user->height}}</p>
                </div>
                <div class="col-2">
                    <p><strong>Talla:</strong> {{$user->size}}</p> 
                </div>
                <div class="col-2">
                    <p><strong>IMC:</strong> {{ round($user->height / ($user->size * $user->size),1) }}</p>
                </div>
                <div class="col-2">
                    <p><strong>Edad:</strong> {{ \Carbon\Carbon::parse($datos->borndate)->age }}</p>
                </div>
            </div> 
        </div>
    </div>
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
      <div class="card-body">
            <div class="row"> 
                    <div class="col-6">
                        <p><strong>Cont. de Emergencia:</strong> {{$user->c_emer}}</p>
                    </div>
                    <div class="col-3">
                        <p><strong>Parentesco:</strong> {{$user->relation}}</p>
                    </div>
                    <div class="col-3">
                        <p class="text-center"><strong>Teléfono:</strong> {{$user->p_emer}}</p>
                    </div>
            </div> 
        </div>
    </div>
</div>
<!--Fin Inf. General-->
<hr class="hr" />
<!--Inf. Patologicas-->
<div class="accordion" id="accordionExample">
  <div class="card">
    <div class="card-header" id="headingOne">
      <h5>Personales Patologicas</h5>
    </div>
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
      <div class="card-body">
            <div class="row"> 
                <div class="col-12">
                    <p><strong>Numero de hospitalizaciones:</strong> {{$user->hospitals}}</p>
                </div>               
            </div>
            <div class="row">
            @if($user->hospitals > 0)
                @for ($i = 1; $i <= $user->hospitals; $i++)
                    <p><strong>¿Qué sucedio?  </strong>{{ $user->{'hsp'.$i} }}</p>
                @endfor
            @endif
            </div> 
        </div>
    </div>
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
        <div class="card-body">
            <div class="row"> 
                <div class="col-6">
                    <p><strong>Cirugias:</strong> {{$user->cirugy}}</p>
                </div>
                <div class="col-6">
                    <p><strong>Patológias:</strong> {{$user->pathology}}</p>
                </div>
            </div> 
            <div class="row"> 
                <div class="col-6">
                    <p><strong>Alergias:</strong> {{$user->alergy}}</p>
                </div>
                <div class="col-6">
                    <p><strong>Traumaticos:</strong> {{$user->trauma}}</p>
                </div>
            </div> 
        </div>
    </div>
</div>
<!--Fin Inf. Patologicas-->
<hr class="hr" />
<!--Inf. Heredo-->
<div class="accordion" id="accordionExample">
  <div class="card">
    <div class="card-header" id="headingOne">
      <h5>Heredo-Familiares</h5>
    </div>
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
      <div class="card-body">
            <div class="row"> 
                <div class="col-12">
                    <h4>Madre</h4>
                    <div id="usertable" class="table-responsive">
                        <table class="table table-hover table-nowrap">
                            <thead class="table-light">
                                    <tr>
                                        <th scope="col">Vivo/Sano</th>
                                        <th scope="col">Fallecido</th>
                                        <th scope="col">Diabetes mellitus</th>
                                        <th scope="col">Hipertensión arterial</th>
                                        <th scope="col">Cardiopatías</th>
                                        <th scope="col">Nefropatías</th>
                                        <th scope="col">Neoplasias</th>
                                    </tr>
                                </thead><tbody>
                                <tr>
                                @if($user->momdat1 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->momdat2 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->momdat3 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->momdat4 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->momdat5 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->momdat6 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->momdat7 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif 
                                </tr>
                            </tbody>
                        </table>           
                    </div>
                </div>
                <div class="row"> 
                <div class="col-12">
                    <h4>Padre</h4>
                    <div id="usertable" class="table-responsive">
                        <table class="table table-hover table-nowrap">
                            <thead class="table-light">
                                    <tr>
                                        <th scope="col">Vivo/Sano</th>
                                        <th scope="col">Fallecido</th>
                                        <th scope="col">Diabetes mellitus</th>
                                        <th scope="col">Hipertensión arterial</th>
                                        <th scope="col">Cardiopatías</th>
                                        <th scope="col">Nefropatías</th>
                                        <th scope="col">Neoplasias</th>
                                    </tr>
                                </thead><tbody>
                                <tr>
                                @if($user->daddat1 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->daddat2 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->daddat3 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->daddat4 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->daddat5 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->daddat6 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif
                                @if($user->daddat7 === 1)
                                    <td>SI</td>
                                    @else
                                    <td>NO</td>
                                @endif 
                                </tr>
                            </tbody>
                        </table>           
                    </div>
                </div>
        </div>
    </div>
</div>
    </div>
</div>
<!--Fin Inf. Heredo-->
<hr class="hr" />
<!--Inf. Mixta-->
<div class="accordion" id="accordionExample">
  <div class="card">
    <div class="card-header" id="headingOne">
      <h5></h5>
    </div>
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
        <div class="card-body">
            <div class="row"> 
            
                <div class="col-6">
                    <h5 class="text-center">No Patológicos</h5>
                @if($user->pers_nopat1 === 1)
                    <p><strong>Tabaquismo: </strong>SI </p>
                    @else 
                    <p><strong>Tabaquismo: </strong>NO </p>    
                @endif
                @if($user->pers_nopat2 === 1)
                    <p><strong>Alcoholismo: </strong>SI </p>
                    @else 
                    <p><strong>Alcoholismo: </strong>NO </p>    
                @endif
                @if($user->pers_nopat3 === 1)
                    <p><strong>Toxicomanías: </strong>SI </p>
                    @else 
                    <p><strong>Toxicomanías: </strong>NO </p>    
                @endif
                </div>
                
                <div class="col-3">
                <h5 class="text-center">Vacunación</h5>    
                @if($user->vacci1 === 1)
                    <p><strong>COVID-19: </strong>SI </p>
                    @else 
                    <p><strong>COVID-19: </strong>NO </p>    
                @endif
                @if($user->vacci2 === 1)
                    <p><strong>Influenza: </strong>SI </p>
                    @else 
                    <p><strong>influenza: </strong>NO </p>    
                @endif
                @if($user->vacci3 === 1)
                    <p><strong>Tétanos: </strong>SI </p>
                    @else 
                    <p><strong>Tétanos: </strong>NO </p>    
                @endif
                </div>
                <div class="col-3">
                    <br>
                @if($user->vacci4 === 1)
                    <p><strong>Neumococo: </strong>SI </p>
                    @else 
                    <p><strong>Neumococo: </strong>NO </p>    
                @endif
                @if($user->vacci5 === 1)
                    <p><strong>Hepatitis B: </strong>SI </p>
                    @else 
                    <p><strong>Hepatitis B: </strong>NO </p>    
                @endif
            </div> 
        </div>
    </div>
</div>
</div>
<!--Fin Inf. Mixta-->
@if($datos->sex === "F")
<hr class="hr" />
<!--Inf. Patologicas-->
<div class="accordion" id="accordionExample">
  <div class="card">
    <div class="card-header" id="headingOne">
      <h5>Gineco obstétricos</h5>
    </div>
    
    <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
        <div class="card-body">
            <div class="row"> 
                <div class="col-6">
                    <p><strong>Ritmo:</strong> {{$user->rythm}}</p>
                </div>
                <div class="col-6">
                    <p><strong>Embarazos:</strong> {{$user->pregnant}}</p>
                </div>
            </div> 
            <div class="row"> 
                <div class="col-6">
                @if($user->prevent1 === 1)
                    <p><strong>Papanicolaou:</strong> SI</p>
                @else
                    <p><strong>Papanicolaou:</strong> NO</p>
                @endif    
                </div>
                <div class="col-6">
                @if($user->prevent2 === 1)
                    <p><strong>Mastografía:</strong> SI</p>
                @else
                    <p><strong>Mastografías:</strong> NO</p>
                @endif 
                </div>
            </div> 
        </div>
    </div>
</div>
<!--Fin Inf. Patologicas-->
@else
<hr class="hr" />
@endif
@endforeach
@endsection