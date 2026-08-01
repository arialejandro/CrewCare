@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row">
        <div class="card container_dash"><br>
            <h4 class="center">Register pcr</h4>
            <form class="form-row"  method='POST' action="{{url('/crearpcr/'.$id)}}" enctype="multipart/form-data">
                {{csrf_field()}}
                <div class="row">
                    <h5 class="center">PCR POSITIVE?</h5>
                   <div class="input-field col s6 push-s2">
                        <p>
                            <label>
                                <input name="estado" type="radio"  value="1" />
                                <span>YES</span>
                            </label>                                
                        </p>
                    </div>
                    <div class="input-field col s6">
                        <p>
                            <label>
                                <input name="estado" type="radio"  value="0" />
                                <span>NO</span>
                            </label>
                        </p>
                    </div> 
                </div>
                <div class="center">
                    <input type="submit" class="waves-effect waves-light btn white-text" value="Register">
                </div><br>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"></div>
                <div class="card-body center">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">fecha</th>
                                <th scope="col">estado</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($userpcr as $item)
                            <tr>
                                <th>{{$item->created_at}}</th>
                                <td>
                                    @if($item->estado)
                                        POSITIVO
                                    @else
                                        NEGATIVO
                                    @endif
                                </td>
                                <td>
                                    <a title="editar" class="waves-effect waves-light btn-small" href="{{url('/editarpcr/'.$item->id_pcr)}}">
                                        <i class="material-icons">
                                        build
                                        </i>
                                    </a>
                                    <form title="desactivar" method="post" action="{{url('/eliminarpcr/'.$item->id_pcr)}}">
                                        {{ csrf_field() }}
                                        <button class="red waves-effect waves-light btn-small" type="submit">
                                            <i class="material-icons">delete</i>
                                        </button>
                                    </form>
                                    
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div>
                        {{ $userpcr->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection