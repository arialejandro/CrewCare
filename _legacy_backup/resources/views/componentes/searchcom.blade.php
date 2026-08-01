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
                        @if($user->enfermo == 0)
                            <tr>
                        @else
                            <tr class="table-warning">
                        @endif

                        <th>
                            @switch ($user->daytest)
                                @case ('2')
                                    <span class="badge bg-success">Espc</span>
                                @break
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
                        @switch($user->daytest)
                            @case ('2')
                            <form class="dropdown-item col-2" title="GroupA" method="post" action="{{url('/putga/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit col-2" class="btn btn-outline-dark"onclick="return confirm('User will switch to group A');">
                                <i class="fa-solid fa-a"></i>
                                </button>
                            </form>
                            <form class="dropdown-item col-2" title="GroupG" method="post" action="{{url('/putgg/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit col-2" class="btn btn-outline-dark"onclick="return confirm('User will switch to group A');">
                                <i class="fa-solid fa-g"></i>
                                </button>
                            </form>
                            @break
                                @case ('3')
                            <form class="dropdown-item" title="GroupB" method="post" action="{{url('/putgb/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('User will switch to group B');">
                                <i class="fa-solid fa-b"></i>
                                </button>
                            </form>
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
                            <form class="dropdown-item" title="GroupB" method="post" action="{{url('/putgb/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-outline-dark" onclick="return confirm('User will switch to group B');">
                                <i class="fa-solid fa-b"></i>
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