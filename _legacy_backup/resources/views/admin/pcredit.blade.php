@extends('layouts.app')



@section('content')

<div class="container">

    <div class="row justify-content-center">

        <div class="col-md-12">

            <div class="card">

                <div class="card-header">
                    {{$userpcr->created_at}}
                </div>

                <div class="card-body center">
                @if ($userpcr->estado == 1)
                    @php ($uno = 'checked')
                    @php ($cero = '')
                @else
                    @php ($uno = '')
                    @php ($cero = 'checked')
                @endif
                    <form action="{{url('/savepcr/'.$userpcr->id_pcr)}}" class="form" method='POST'>
                        {{ csrf_field() }}
                        <div class="input-field col s6 push-s2">
                            <p>
                                <label>
                                    <input name="estado" type="radio"  value="1"  {{ $uno }}/>
                                    <span>YES</span>
                                </label>                                
                            </p>
                        </div>
                        <div class="input-field col s6">
                            <p>
                                <label>
                                    <input name="estado" type="radio"  value="0"  {{ $cero }}/>
                                    <span>NO</span>
                                </label>
                            </p>
                        </div>
                        <div class="center">
                            <button type="submit" value="editar" class="waves-effect waves-light btn-small">Guardar</button>
                        </div><br>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection