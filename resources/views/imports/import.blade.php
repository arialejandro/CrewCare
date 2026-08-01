@extends('layouts/app')
@section('content')

<div class="containe">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Import Crew Members</div>
                <div class="card-body">
                    <div class="alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            {{$error}}
                        @endforeach
                    </div>

                    <form action="{{route('crewstore')}}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="import_file">
                        <button class="btn btn-warning" type="submit">Import</button>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</div>


@endsection