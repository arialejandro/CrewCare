<div>
    
   
<div class="p-10 bg-surface-secondary">
    <div class="card">
            <div class="card-header">
                <h4>CrewList</h4>
            </div>
        <div id="usertable" class="table-responsive">
            <table class="table table-hover table-nowrap">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Zone</th>
                        <th scope="col">F.Name</th>
                        <th scope="col">L. Name</th>
                        <th scope="col">Departament</th>
                        <th scope="col">DOB</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Sex</th>
                        <th scope="col">Email</th>
                        <th scope="col">Group</th>
                        <th scope="col">Actions</th>
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
                            @switch ($user->zone)
                                @case ('1A')
                                    <span class="badge bg-success">{{$user->zone}}</span>
                                @break
                                @case ('1B')
                                    <span class="badge bg-danger">{{$user->zone}}</span>
                                @break
                                @case ('2')
                                    <span class="badge bg-warning">{{$user->zone}}</span>
                                @break
                                @case ('3')
                                    <span class="badge bg-dark">{{$user->zone}}</span>
                                @break
                                    @default
                                    <span class="badge bg-secondary">No Zone</span>
                                @endswitch
                        </th>
                        <td>{{$user->name}}</td>
                        <td>{{$user->lname}}</td>
                        <td>{{$user->puestodepartamento}}</td>
                        <td>{{$user->borndate}}</td>
                        <td>{{$user->phone}}</td>
                        <td>{{$user->sex}}</td>
                        <td>{{$user->email}}</td>
                        @switch($user->daytest)
                            @case ('2')
                            <td>B</td>
                        @break
                            @case ('3')
                            <td>A</td>
                        @break
                            @default
                            <td>N/G</td>
                        @endswitch
                        <td>
                        <div class="dropdown"> <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false"> Actions </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li>
                            @switch($user->daytest)
                            @case ('2')
                            <form class="dropdown-item col-2" title="GroupA" method="post" action="{{url('/putga/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit col-2" class="btn btn-outline-dark"onclick="return confirm('User will switch to group A');">
                                <i class="fa-solid fa-a"></i>
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
                            </li>
                            <li>
							@if($user->enfermo === 1)
                            <form class="dropdown-item" title="NotSick" method="post" action="{{url('/notsick/'.$user->id)}}">
                            {{ csrf_field() }}
                            <button type="submit" class="btn btn-outline-dark" onclick="return confirm('Are you sure about this action??');">
                                <i class="fa-solid fa-virus-slash"></i> &nbsp Not Sick
                                
                             @endif
                             </button>
                            </form>
							</li>
                            <li>
							<a title="config" class="dropdown-item" href="{{url('/idcard/'.$user->id)}}">
                            <i class="fa-solid fa-address-card"></i>&nbsp Id Card
                            </a>
							</li>
                            <li>
							<a title="config" class="dropdown-item" href="{{url('/useredit/'.$user->id)}}">
                            <i class="fa-solid fa-user-pen"></i>&nbsp Edit
                            </a>
							</li>
                            <li>
                            <a title="History" class="dropdown-item" href="{{url('/historial/'.$user->id)}}">
                            <i class="fa-solid fa-notes-medical"></i>&nbsp History Test
                            </a>
                            </li>
                            <li>
                            <a title="History" class="dropdown-item" href="{{url('/historialWR/'.$user->id)}}">
                            <i class="fa-solid fa-book"></i>&nbsp History WR
                            </a>
                            </li>
                            <li>
							 @if($user->admin === 0)
                                <a title="Activar encuesta" class="dropdown-item" href="{{url('/activaradmin/'.$user->id)}}">
                                <i class="fa-solid fa-lock-open"></i>&nbsp Add Admin
                                </a>
                            @else
                                <a title="Quit admin" class="dropdown-item" href="{{url('/desactivaradmin/'.$user->id)}}">
                                <i class="fa-solid fa-lock"></i>&nbsp Quit Admin
                                </a>
                            @endif
							</li>
                            <li>
							@if($user->encuestadiaria === 1)
                                <a title="Activate WR" class="dropdown-item" href="{{url('/activarencuesta/'.$user->id)}}">
                                <i class="fa-solid fa-file-invoice"></i> &nbsp Activate WR
                                </a>
                             @endif
							</li>
                            @if($user->activo === 1)
                            <form class="dropdown-item" title="Deactivate" method="post" action="{{url('/desactivarusuario/'.$user->id)}}">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Desea desactivar el usuario?');">
                                <i class="fa-solid fa-plane-departure"></i> &nbsp Deactivate
                                </button>
                            </form>      
                            @else
                            <a title="Activate" class="btn btn-success dropdown-item" href="{{url('/activarusuario/'.$user->id)}}">
                            <i class="fa-solid fa-plane-arrival"></i>&nbsp Activate
                            </a>
                                @endif
                            </li>
                        </ul>
		
                    </div>
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