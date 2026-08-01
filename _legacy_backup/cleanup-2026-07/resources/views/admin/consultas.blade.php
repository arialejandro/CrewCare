@extends('layouts.app')

@section('content')

    <script src="{{ asset('js/departamentosdinamicos.js') }}"></script>
    <div class="container">
        <br>
        <div class="row">
            <div class="col-md-6 blue-grey">
                <select name="id_departamentos" class=""> 
                    <option value="0">Departamentos</option>
                        @foreach ($departamentos as $item)
                            <option value="{{$item->id_departamentos}}">{{$item->departamento}}</option>
                        @endforeach
                </select> 
            </div>
        </div>
        <div class="row">
            <div id="usuariosdepartamento" class="col-md-6 blue-grey">

            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var elems = document.querySelectorAll('select');
            var instances = M.FormSelect.init(elems);
        });

    </script>
@endsection