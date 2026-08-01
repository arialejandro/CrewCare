<div id="usertable" class="table-responsive">
            <table class="table table-hover table-nowrap">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Zone</th>
                        <th scope="col">F.Name</th>
                        <th scope="col">L. Name</th>
                        <th scope="col">L. Name 2</th>
                        <th scope="col">Consecutive</th>
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
                        <td>{{$user->lname2}}</td>
                        <td>{{$user->labn}}</td>
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
                            <a title="Queue" class="dropdown-item" href="{{url('/userqueue/'.$user->id)}}">
                            <i class="fa-solid fa-notes-medical"></i>&nbsp Queue
                            </a>
                            
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div>
                {{ $usuarios->links() }}
            </div>
        </div>