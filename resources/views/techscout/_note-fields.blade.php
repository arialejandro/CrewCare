{{-- Campos de una NOTA. Se usan en DOS sitios y por eso viven aquí:
       · dentro del formulario de ALTA (la primera nota se guarda junto con el scouting), y
       · en su propio formulario una vez que el scouting existe.
     Tenerlos en un solo archivo evita que las dos copias se desincronicen — que es como empiezan
     a divergir las cosas (ya nos pasó con el título de la pestaña y con el autor del documento).
     Params: $lastLabel (etiqueta arrastrada), $submitLabel (texto del botón). --}}
<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label fw-semibold" for="photo">Foto</label>
        {{-- SIN `capture`: en set hacen falta las dos opciones, cámara y galería. --}}
        <input type="file" name="photo" id="photo" class="form-control"
               accept="image/*,.heic,.heif" data-cc-photo>
    </div>
    <div class="col-md-7">
        <label class="form-label fw-semibold" for="note">Qué hay que resolver</label>
        <textarea name="note" id="note" class="form-control" rows="3" maxlength="2000"
                  placeholder="Ej. «Quitar las cortinas de esta ventana»">{{ old('note') }}</textarea>
    </div>
    <div class="col-md-7">
        <label class="form-label fw-semibold" for="story_label">Nombre en la historia <span class="cc-muted fw-normal">(opcional)</span></label>
        {{-- Se arrastra de la nota anterior: en multilocación se escribe UNA vez por espacio. Se
             descartó etiquetar por área física porque no generaliza: una bodega no tiene los
             cuartos de una casa. --}}
        <input type="text" name="story_label" id="story_label" class="form-control"
               value="{{ old('story_label', $lastLabel ?? null) }}" maxlength="120" placeholder="Ej. «Depa Pablo»">
        <small class="cc-muted d-block mt-1">Se mantiene para las siguientes. Bórralo al cambiar de espacio.</small>
    </div>
    @isset($submitLabel)
        <div class="col-md-5 d-flex align-items-start">
            <button type="submit" class="btn btn-crew-accent mt-md-4">{{ $submitLabel }}</button>
        </div>
    @endisset
</div>
