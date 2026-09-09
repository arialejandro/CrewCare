{{--
    Sello estilo CFDI. Nació en el paso Injury (2026-07-20) y desde el 2026-07-22 lo comparten
    también el DSR y cualquier documento sellable, para que la firma se vea IGUAL en todos.

    ⚠ DOS FIRMAS DISTINTAS, y este parcial sólo pinta la primera:
      (a) FIRMA SHA = sello de INTEGRIDAD del sistema. Automática, sin intervención humana.
          Es lo que dice "este documento no ha cambiado desde que se selló". Eso es esto.
      (b) FIRMA AUTÓGRAFA del Safety / testigo / lesionado = MÓDULO FUTURO, todavía no existe.
          Su hueco es el bloque `.sign` de cada vista (espacio en blanco sobre una línea, con
          nombre y rol debajo), que YA se imprime y ya se puede firmar a mano sobre el papel.
    Por eso aquí NUNCA se escribe "pendiente de firma": el documento no está esperando nada.

    Parámetros:
      $doc     (Model) documento sellable (usa HasDigitalSignatures). Alias: $injuryReport.
      $folio   (string) folio legible del documento (p. ej. 'INJ-0002' o 'DSR-0005').
      $prefix  (string) prefijo de la cadena original (p. ej. 'CREWCARE-INJ' / 'CREWCARE-DSR').

    Pinta:
      1) Banner de integridad SÓLO CUANDO HAY ALGO QUE MIRAR (alterado / aún sin sellar).
      2) Recuadro estilo CFDI (sólo si hay sello): QR al verificador público a la izquierda +
         sello digital (SHA-256) · cadena original · metadatos (folio · UUID · sellado · firmó)
         al centro + identicon del hash a la derecha. Monoespaciado y cotejable por terceros.
--}}
@php
    use Illuminate\Support\Facades\Schema;

    // $doc es el nombre nuevo; $injuryReport se acepta por compatibilidad con las vistas
    // del Injury, que lo pasaban así desde el 2026-07-20.
    $__doc = isset($doc) ? $doc : (isset($injuryReport) ? $injuryReport : null);

    $__hasSigTable = $__doc !== null && Schema::hasTable('digital_signatures');
    $__sig = $__hasSigTable ? $__doc->verifyLatestSignature() : null;
    $__rec = $__hasSigTable ? $__doc->signatures()->latest('id')->first() : null;

    $__folio    = isset($folio) ? $folio : ($__doc ? 'INJ-' . str_pad((string) $__doc->id, 4, '0', STR_PAD_LEFT) : '—');
    $__prefix   = isset($prefix) ? $prefix : 'CREWCARE-INJ';
    $__uuid     = ($__doc && $__doc->uuid) ? $__doc->uuid : null;
    $__sealedAt = ($__rec && $__rec->signed_at) ? \Carbon\Carbon::parse($__rec->signed_at)->format('d/m/Y H:i:s') : null;
    // Cadena original estilo CFDI (cotejable): prefijo | versión | uuid | folio | sellado ISO | hash.
    $__cadena = $__rec
        ? '||' . $__prefix . '|1.0|' . ($__uuid ?: '-') . '|' . $__folio . '|'
            . ($__rec->signed_at ? \Carbon\Carbon::parse($__rec->signed_at)->format('Y-m-d\TH:i:s') : '-')
            . '|' . $__rec->document_hash . '||'
        : null;
    // (2026-07-24) QR al VERIFICADOR PÚBLICO + IDENTICON del hash.
    //   · El QR lleva tipo+uuid porque los UUID v4 no dicen de qué tabla son. La URL la arma
    //     SealVerifier desde la CLASE del modelo, así que ninguna de las 6 vistas tuvo que
    //     aprender un parámetro nuevo. Si el documento no tiene uuid, no se pinta: mejor sin QR
    //     que con un enlace muerto impreso en papel.
    //   · El IDENTICON es VERIFICABILIDAD HUMANA, no criptografía: nadie coteja 64 caracteres a
    //     ojo, pero dos dibujos distintos se notan al instante, en papel y sin internet. Se
    //     enuncia como "sello visual derivado del hash", nunca como verificación adicional.
    $__verifyUrl = $__rec ? \App\Support\SealVerifier::urlFor($__doc) : null;
    $__qr        = $__verifyUrl ? \App\Support\SealVerifier::qrSvg($__verifyUrl, 132) : null;
    $__identicon = $__rec ? \App\Support\SealVerifier::identiconSvg($__rec->document_hash, 56) : null;

    // (2026-08-12) El sello muestra el NOMBRE DE CRÉDITOS del firmante (ncreditos; si vacío, nombre
    // corto), homologado con las firmas y el pie del documento — no el nombre legal completo.
    $__signer = ($__rec && $__rec->user) ? \App\Models\User::displayName($__rec->user) : null;
    // (2026-09-08) Junto al nombre va el PUESTO, no el ROL DE SISTEMA. Aquí se imprimía
    // `role_at_signing` y el sello decía "Ari Rómulo · super-admin": a quien lee un documento de
    // seguridad no le dice nada que el firmante tenga permisos de administrador — le importa QUÉ
    // ES en la producción (Coordinador de Seguridad, Gerente de Producción…). El rol es una
    // categoría interna de permisos; el puesto es el cargo por el que esa persona responde.
    // Se lee el puesto VIGENTE y no una foto congelada al firmar, a propósito: una instancia es
    // UNA producción y el puesto de alguien no cambia mientras dura. Si no tiene puesto
    // registrado no se pinta nada (regla: lo que no existe, no se muestra).
    $__role   = ($__rec && $__rec->user) ? $__rec->user->positionName() : null;
    // Sello de SISTEMA: no lo firmó una persona, lo emitió la app al cerrarse el día.
    $__system = $__rec && $__rec->user_id === null;
@endphp

{{-- 1) Banner de integridad — SÓLO EL CASO ANORMAL.
     (2026-07-24, decisión del owner) El cintillo verde "Documento firmado — integridad
     verificada" DESAPARECE. Un documento que coincide con su sello no tiene nada que anunciar:
     gritar "todo bien" en cada impresión gasta la atención del lector, y cuando algo SÍ está
     mal el cintillo rojo ya no destaca porque el ojo aprendió a ignorar esa franja. Se avisa
     cuando hay algo que mirar. Que el documento está sellado se prueba abajo, con el hash, la
     cadena original y el QR — que es la prueba, no el aviso. --}}
@if($__sig === false)
<div class="seal bad">@include('componentes._icon', ['name' => 'shield-alert'])<div><b>{{ __('reports.injury_altered') }}</b></div></div>
@elseif($__sig === null)
{{-- Sin sello TODAVÍA. Éste SÍ se sigue diciendo, y no es una excepción a la regla de arriba:
     no hay recuadro CFDI que pintar, así que sin esta línea el documento saldría mudo y se
     leería igual que uno sellado. Se enuncia como un hecho del documento, nunca como una
     espera: no está "pendiente de que alguien firme" — la firma autógrafa es otra cosa y vive
     en el bloque .sign. Los reportes anteriores al código de sellado (2026-07-12) caen aquí
     para siempre, y eso es correcto: nunca se sellaron. --}}
<div class="seal none">@include('componentes._icon', ['name' => 'info'])<div><span class="h">{{ __('reports.seal_not_sealed') }}</span></div></div>
@endif

{{-- 2) Recuadro estilo CFDI (sólo si el documento ya está firmado) --}}
@if($__rec)
<div class="cfdi">
  {{-- IZQUIERDA: el QR al verificador público ocupa el lugar que tenía el escudo decorativo
       (decisión owner 2026-07-24). El escudo sólo ilustraba; el QR es lo único de este bloque
       que un tercero puede USAR sin entrar a la app, así que se lleva el mejor lugar. Cuando
       el documento no tiene uuid (nunca se le pudo armar URL) vuelve el escudo, para que el
       recuadro no se descuadre y el sello siga leyéndose como sello. --}}
  <div class="cfdi-mark{{ $__qr ? ' cfdi-mark--qr' : '' }}">
    @if($__qr)
      {!! $__qr !!}
    @else
      <div class="m">@include('componentes._icon', ['name' => 'shield'])</div>
      <div class="lbl">SELLO<br>SHA-256</div>
    @endif
  </div>
  <div class="cfdi-body">
    <div class="cfdi-row">
      <div class="cfdi-k">{{ __('reports.seal_digital_label') }}</div>
      <div class="cfdi-v">{{ $__rec->document_hash }}</div>
    </div>
    <div class="cfdi-row">
      <div class="cfdi-k">{{ __('reports.seal_cadena_label') }}</div>
      <div class="cfdi-v">{{ $__cadena }}</div>
    </div>
    <div class="cfdi-meta">
      <span><b>{{ __('reports.label_folio') }}:</b> {{ $__folio }}</span>
      <span><b>UUID:</b> <span class="mono">{{ $__uuid ?: '—' }}</span></span>
      <span><b>{{ __('reports.seal_label_sealed_at') }}:</b> {{ $__sealedAt ?: '—' }}</span>
      @if($__system)<span><b>{{ __('reports.seal_signed_by') }}:</b> {{ __('reports.seal_by_system') }}</span>
      @elseif($__signer)<span><b>{{ __('reports.seal_signed_by') }}:</b> {{ $__signer }}@if($__role) · {{ $__role }}@endif</span>@endif
    </div>
  </div>
  @if($__identicon)
  {{-- DERECHA: el identicon, en el sitio donde antes estaba el QR y en su tamaño de siempre
       (56px). SVG inline —la CSP bloquea CDNs y esto se imprime en papel— y SIN pie de imagen:
       un dibujo que sólo sirve para comparar de un vistazo no necesita que le expliquen nada;
       el rótulo únicamente añadía ruido al documento impreso. --}}
  <div class="cfdi-verify">
    <div class="cfdi-idc">{!! $__identicon !!}</div>
  </div>
  @endif
</div>
@endif
