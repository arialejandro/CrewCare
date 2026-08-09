{{-- ============================================================================================
     Nota del editor por apartado (Wrap, Bloque 2).
       · SELLADA: cuando el payload trae la nota, se pinta aquí (frozen) y se imprime.
       · BORRADOR: nace vacía (.is-empty → no ocupa espacio) y el JS del panel la rellena EN VIVO
         por [data-note-out] mientras el editor escribe.
     El rótulo "Nota del editor —" es un ::before (CSS), así que el JS puede sobrescribir sólo el
     texto con textContent sin borrar el rótulo.
     Params: $wnKey (s1..s8) · $wnTxt (texto, opcional).
============================================================================================ --}}
@php $wnTxt = isset($wnTxt) ? trim((string) $wnTxt) : ''; @endphp
<div class="wsec-note{{ $wnTxt === '' ? ' is-empty' : '' }}" data-note-out="{{ $wnKey }}">{{ $wnTxt }}</div>
