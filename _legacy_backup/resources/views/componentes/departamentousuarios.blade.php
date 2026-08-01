        <div class="col-md-12">
            <div class="card">
                <div class="card-header"></div>
                <div class="card-body center">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">#clave</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Correo</th>
                                <th scope="col">Edad</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($usuarios as $user)
                            <tr>
                                <th>{{$user->id}}</th>
                                <td>{{$user->name}}</td>
                                <td>{{$user->email}}</td>
                                <td>{{$user->age}}</td>
                                <td>
                                    <a title="historial" class="waves-effect waves-light btn-small" href="{{url('/historial/'.$user->id)}}">
                                        <i class="material-icons">description</i>
                                    </a>
                                    @if($user->activo === 0)
                                    <a title="Activar" class="waves-effect waves-light btn-small" href="{{url('/activarusuario/'.$user->id)}}">
                                        <i class="material-icons">play_arrow</i>
                                    </a>
                                    @else
                                    <form title="desactivar" method="post" action="{{url('/desactivarusuario/'.$user->id)}}">
                                        {{ csrf_field() }}
                                        <button class="red waves-effect blue-grey btn-small" type="submit" onclick="return confirm('¿Desea desactivar el usuario?');">
                                            <i class="material-icons">delete</i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
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
