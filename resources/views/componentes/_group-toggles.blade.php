{{-- Toggles de grupo legacy (putga/putgg) según $user->daytest.
     Extraído de usuarioscrud + search-results para no duplicar el bloque.

     PASO A (2026-07-19): ELIMINADO el botón "Convertir a Médico" (route('putgb') →
     POST /putmed → CrewStatusController@putgb → daytest = 2). Nunca marcó a un médico:
     los 13 usuarios que llegaron a tener el 2 eran de producción/coordinación, y los
     médicos reales tenían NULL. "Ser médico" es ahora, y solo, el rol Spatie `medic`
     (User::isMedic()). El dato se neutralizó con
     database/owner-apply/2026-07-19-daytest-neutralizar-valor-2.sql (2 → NULL), por eso
     ya no hace falta la rama @elseif($user->daytest === 2): sin ese SQL, quien siguiera
     en 2 se quedaría sin ninguna rama y sin botones.

     Para hacer médico a alguien: pantalla "Asignar Roles" (/rolescrud). --}}
<li>
    @if(is_null($user->daytest) || $user->daytest != 1)
        <form action="{{ route('putgg', $user->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" title="Convertir a Supervisor" class="dropdown-item">
                <i class="fa-solid fa-hard-hat"></i>&nbsp Supervisor
            </button>
        </form>
    @else
        <form action="{{ route('putga', $user->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" title="Convertir a Admin" class="dropdown-item">
                <i class="fa-solid fa-gear"></i>&nbsp Admin
            </button>
        </form>
    @endif
</li>
