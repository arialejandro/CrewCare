<!-- Modal Structure -->

<div id="modelformulario{{ $user->id_formulario }}" class="modal">

  <div class="modal-content">

    <h5 class="text-modal-hlp">{{ $user->created_at }}</h5>



        <div class="row">

            <div class="col s6 m2 right-align text-modal-hlp">
                <p>Sintomas: </p>
                <p>¿Haz convivido con gente enferma por COVID-19 en las ultimas horas?:</p>
                <p>¿Cómo te transportaste hoy para llegar al trabajo?:</p>
            </div>

            <div class="col s6 m3">
                @if ($user->sintoma1)
                    <p>NINGUNO</p>
                @else
                    @if ($user->sintoma2)
                     <p>FIEBRE</p>
                    @endif
                    @if ($user->sintoma3)
                        <p>TOS SECA</p>
                    @endif
                    @if ($user->sintoma4)
                        <p>DELOR DE CABEZA</p>
                    @endif
                    @if ($user->sintoma5)
                        <p>DOLOR EN ARTICULACIONES</p>
                    @endif
                    @if ($user->sintoma6)
                        <p>ARGOR DE GARGANTA</p>
                    @endif
                    @if ($user->sintoma7)
                        <p>OJOS ROJOS</p>
                    @endif
                    @if ($user->sintoma8)
                        <p>ESCURRIMIENTO NASAL</p>
                    @endif
                    @if ($user->sintoma9)
                        <p>DIFICULTAD PARA RESPIRAR</p>
                    @endif
                    @if ($user->sintoma10)
                        <p>PERDIDA DEL GUSTO U OLFATO</p>
                    @endif
                @endif
                <p>{{ $user->convivencia }}</p>
                <p>{{ $user->transporte }}</p>
            </div>

        </div>

  </div>

  <div class="modal-footer">

    <a href="#!" class="modal-close waves-effect waves-green btn-flat">Cerrar</a>

  </div>

</div>

