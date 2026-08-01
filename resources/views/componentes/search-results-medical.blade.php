{{-- Respuesta AJAX del buscador de la lista médica (SearchController@medical).
     El front la inyecta tal cual en #usertable. (2026-07-31) Delega en el MISMO parcial que el
     render inicial (_medical-directory): crew + personas sin cuenta + estado vacío con el registro,
     para que buscar no cambie el diseño a media pantalla y encuentre a ambos tipos de paciente. --}}
@include('componentes._medical-directory', [
    'usuarios'     => $usuarios,
    'litePatients' => $litePatients ?? collect(),
    'liteCounts'   => $liteCounts ?? [],
])
