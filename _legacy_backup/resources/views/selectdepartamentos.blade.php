<select name="id_departamentos" style="display:block;"> 
    <option value="0">Elija su departamento</option>
        @foreach ($select as $item)
            <option value="{{$item->id_departamentos}}">{{$item->departamento}}</option>
        @endforeach
</select>
@if ($errors->has('id_departamentos'))
    <small class="form-text text-danger">{{ $errors->first('id_departamentos') }}</small>
@endif