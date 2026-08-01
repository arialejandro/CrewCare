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
                <h4>CrewList</h4>
            </div>
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
                fetch('/searchqueue/'+valor+'/?page=1',{
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