@extends('layouts.app')



@section('content')

<div class="container p-5">
    <div class="row">
        <div class="p-10 bg-surface-secondary">
            <div class="card">
            <div class="card-header">
                <h4>History Record </h4> 
            </div>
            <div id="usertable" class="table-responsive">
                <table class="table table-hover table-nowrap">
                     <thead class="table-light">
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($usuarios as $user)
                            <tr>
                                <td>{{$user->created_at}}</td>
                                <td>
                                    @if ($user->resultado ===  0)
                                    <span class="badge bg-success">Negative</span>
                                    @else
                                    <span class="badge bg-danger">Positive</span>
                                        @endif
                                        
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
</div>

@endsection